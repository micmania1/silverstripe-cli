<?php

namespace micmania1\SilverStripeCli\Docker;

use Http\Client\Common\Exception\ClientErrorException;
use Docker\Docker;
use Docker\API\Model\Buildsuccess;
use Docker\API\Model\Container;
use Docker\API\Model\Image;
use Docker\Manager\ContainerManager;
use Docker\Manager\ImageManager;
use Docker\Context\Context;
use Docker\API\Model\ExecConfig;
use Docker\API\Model\ExecStartConfig;
use Symfony\Component\Console\Exception\RuntimeException;
use micmania1\SilverStripeCli\Docker\ServiceInterface;
use micmania1\SilverStripeCli\Console\OutputInterface;
use micmania1\SilverStripeCli\Helpers\Spinner;
use micmania1\SilverStripeCli\Model\Project;

/**
 * Shared functionality for all docker services
 */
abstract class AbstractService implements ServiceInterface
{
    /**
     * Unique identifier for this service
     *
     * @var string
     */
    protected $name;

    /**
     * @var Docker;
     */
    protected $docker;

    /**
     * @return Docker\Context\ContextBuilder|null
     */
    abstract protected function getImageBuilder($config = []);

    /**
     * @return Docker\Manager\ContainerConfig
     */
    abstract protected function getContainerConfig($config = []);

    /**
     * @return string
     */
    abstract protected function getImageName();

    /**
     * @param Context $context
     */
    abstract protected function copyFixtures(Context $context);

    /**
     * @param string $name
     * @param Docker $docker
     */
    public function __construct($name, Docker $docker)
    {
        $this->name = $name;
        $this->docker = $docker;
    }

    public function build(OutputInterface $output, $config = [])
    {
        if (!$this->imageExists()) {
            $this->buildImage($output, $config);
        }

        if (!$this->containerExists() && $this->imageExists()) {
            $this->buildContainer($output, $config);
        }

        return $this->containerExists();
    }

    public function exists()
    {
        return $this->imageExists() && $this->containerExists();
    }

    public function status(OutputInterface $output)
    {
        $container = $this->getContainerManager()->find($this->getName());
        if (!$container) {
            $output->emptyLine();
            $output->writeln(' The environment is not ready. Run env:up');
            $output->emptyLine();
            return;
        }

        $state = $container->getState();

        if ($state->getPaused()) {
            $status = ServiceInterface::STATUS_PAUSED;
            $type = 'warning';
        } elseif ($state->getRestarting()) {
            $status = ServiceInterface::STATUS_RESTARTING;
            $type = 'warning';
        } elseif ($state->getRunning()) {
            $status = ServiceInterface::STATUS_RUNNING;
            $type = 'success';
        } else {
            $status = ServiceInterface::STATUS_STOPPED;
            $type = 'error';
        }

        $message = sprintf('%s status', ltrim($container->getName(), '/'));
        $output->writeStatus($message, $status, $type);
        $output->emptyLine();
    }

    public function start(OutputInterface $output, array $config = [])
    {
        $output->writeStatus(sprintf('Starting environment %s', $this->getName()));
        try {
            $response = $this->getContainerManager()->start($this->getName());

            $output->clearLine();
            $output->writeStatus(
                sprintf('Starting environment %s', $this->getName()),
                'OK',
                'success'
            );
            $output->emptyLine();
        } catch (ClientErrorException $e) {
            $output->clearLine();
            $output->writeStatus(
                sprintf('Starting environment %s', $this->getName()),
                'FAIL',
                'error'
            );
            $output->emptyLine();

            throw $e;
        }
    }

    public function stop(OutputInterface $output)
    {
        $message = sprintf('Stopping environment %s', $this->getName());
        $output->writeStatus($message);
        try {
            $this->getContainerManager()->stop($this->getName());

            $output->clearLine();
            $output->writeStatus($message, 'OK', 'success');
            $output->emptyLine();
        } catch (ClientErrorException $e) {
            $output->clearLine();
            $output->writeStatus($message, 'FAIL', 'error');
            $output->emptyLine();

            throw $e;
        }
    }

    public function destroy()
    {
        $this->getContainerManager()->remove($this->getName());
    }

    public function import()
    {
        throw new RuntimeException('import is not implemented');
    }

    public function export()
    {
        throw new RuntimeException('export is not implemented');
    }

    public function getName()
    {
        return $this->name;
    }

    public function getIp()
    {
        $container = $this->getContainer();
        if (!$container) {
            throw new RuntimeException('Containier does not exist');
        }

        $ip = $container->getNetworkSettings()->getIPAddress();
        if (empty($ip)) {
            throw new RuntimeException('Unable to obtain database ip address');
        }

        return $ip;
    }

    /**
     * Returns an array of environment variables
     *
     * @return array
     */
    public function getEnvVars()
    {
        $container = $this->getContainer();
        if (!$container) {
            return [];
        }

        $raw = $container->getConfig()->getEnv();
        $vars = [];
        foreach ($raw as $var) {
            $split = explode('=', $var, 2);
            $vars[$split[0]] = $split[1];
        }

        return $vars;
    }

    protected function exec(OutputInterface $output, $cmd)
    {
        $cmd = explode(' ', $cmd);
        $id = $this->getContainer()->getId();

        $execConfig = new ExecConfig();
        $execConfig->setCmd($cmd);

        $execManager = $this->docker->getExecManager();
        $response = $execManager->create($id, $execConfig);

        $startConfig = new ExecStartConfig();
        $startConfig->setDetach(true);
        $execManager->start($response->getId(), $startConfig);
    }

    protected function buildImage(OutputInterface $output, $config = [])
    {
        $manager = $this->getImageManager();

        $builder = $this->getImageBuilder($config);

        $context = $builder->getContext();

        $this->copyFixtures($context);

        $params = ['t' => $this->getImageName()];

        $message = 'Creating base image';
        try {
            $response = $manager->build(
                $context->toStream(),
                $params,
                ImageManager::FETCH_RESPONSE
            );
            $stream = $response->getBody();

            $spinner = new Spinner($output, $message);
            while (!$stream->eof()) {
                $output->writeln(
                    $stream->read(128),
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $spinner->tick();
            }

            $output->clearLine();
            $output->writeStatus($message, 'OK', 'success');
            $output->emptyLine();
        } catch (ClientErrorException $e) {
            $output->clearLine();
            $output->writeStatus($message, 'FAIL', 'error');
            $output->writeln('');
            throw $e;
        }
    }

    protected function buildContainer(OutputInterface $output, $config = [])
    {
        $manager = $this->getContainerManager();
        $config = $this->getContainerConfig($config);

        $params = ['name' => $this->getName()];
        $response = $manager->create(
            $config,
            $params,
            ContainerManager::FETCH_STREAM
        );
        $stream = $response->getBody();

        $message = 'Building your container';
        $spinner = new Spinner($output, $message);
        while (!$stream->eof()) {
            $output->writeln(
                $stream->read(128),
                OutputInterface::VERBOSITY_VERBOSE
            );
            $spinner->tick();
        }
        $output->clearLine();
        $output->writeStatus($message, 'OK', 'success');
        $output->emptyLine();
    }

    /**
     * Copy a fixtures file to the build dir
     *
     * @param string $filename
     * @param string $buildDir
     *
     * @return boolean
     */
    protected function copyFixture($filename, $buildDir)
    {
        return copy(
            FIXTURES_DIR . DIRECTORY_SEPARATOR . $filename,
            $buildDir . $filename
        );
    }

    /**
     * @return Docker\Manager\ImageManager
     */
    protected function getImageManager()
    {
        return $this->docker->getImageManager();
    }

    /**
     * @return Docker\Manager\ContainerManager
     */
    protected function getContainerManager()
    {
        return $this->docker->getContainerManager();
    }

    /**
     * Checks if the docker image exists
     *
     * @return boolean
     */
    protected function imageExists()
    {
        try {
            $image = $this->getImageManager()->find($this->getImageName());

            return $image instanceof Image;
        } catch (ClientErrorException $e) {
            return false;
        }
    }

    /**
     * Checks if the docker container exists
     *
     * @return boolean
     */
    protected function containerExists()
    {
        return $this->getContainer() instanceof Container;
    }

    /**
     * Fetch the container
     *
     * @return Container|false
     */
    protected function getContainer()
    {
        try {
            $container = $this->getContainerManager()->find($this->getName());

            return $container;
        } catch (ClientErrorException $e) {
            return false;
        }
    }
}
