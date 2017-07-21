<?php

namespace micmania1\SilverStripeCli\Docker;

use Http\Client\Common\Exception\ClientErrorException;
use Docker\Docker;
use Docker\API\Model\Container;
use Docker\API\Model\Image;
use Docker\Manager\ImageManager;
use Docker\Manager\ContainerManager;
use Docker\Context\Context;
use Docker\API\Model\ExecConfig;
use Docker\API\Model\ExecStartConfig;
use Symfony\Component\Console\Exception\RuntimeException;
use micmania1\SilverStripeCli\Docker\ServiceInterface;
use micmania1\SilverStripeCli\Console\OutputInterface;

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
     * @param array $config
     *
     * @return Docker\Context\ContextBuilder|null
     */
    abstract protected function getImageBuilder(array $config = []);

    /**
     * @param array $config
     *
     * @return Docker\Manager\ContainerConfig
     */
    abstract protected function getContainerConfig(array $config = []);

    /**
     * @return string
     */
    abstract protected function getImageName();

    /**
     * @param string $name
     * @param Docker $docker
     */
    public function __construct($name, Docker $docker)
    {
        $this->name = $name;
        $this->docker = $docker;
    }

    /**
     * {@inheritdoc}
     */
    public function imageExists()
    {
        return $this->getImage() instanceof Image;
    }

    /**
     * {@inheritdoc}
     */
    public function containerExists()
    {
        return $this->getContainer() instanceof Container;
    }

    /**
     * {@inheritdoc}
     */
    public function build(OutputInterface $output, array $config = [])
    {
        if (!$this->imageExists() && !$this->buildImage($output, $config)) {
            // throw new RuntimeException('Unable to build image');

            return false;
        }

        if (!$this->containerExists() && !$this->buildContainer($output, $config)) {
            // throw new RuntimeException('Unable to build container');

            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(OutputInterface $output)
    {
        $container = $this->getContainer();
        if (!$container) {
            return false;
        }

        $state = $container->getState();

        return $state->getRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function start(OutputInterface $output, array $config = [])
    {
        try {
            $this->getContainerManager()->start($this->getName());

            return true;
        } catch (ClientErrorException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(OutputInterface $output)
    {
        try {
            $this->getContainerManager()->stop($this->getName());

            return true;
        } catch (ClientErrorException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroyContainer(OutputInterface $output)
    {
        $this->getContainerManager()->remove($this->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function removeImage(OutputInterface $output)
    {
        $this->getImageManager()->remove($this->getImageName());
    }

    /**
     * {@inheritdoc}
     */
    public function import()
    {
        throw new RuntimeException('import is not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function export()
    {
        throw new RuntimeException('export is not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * Execute a command on the docker container
     *
     * @param OutputInterface @output
     * @param string $cmd
     *
     * @return boolean
     */
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

        return true;
    }

    /**
     * Build the docker image
     *
     * @param OutputInterface $output
     * @param array $config
     *
     * @return boolean
     */
    protected function buildImage(OutputInterface $output, array $config = [])
    {
        $manager = $this->getImageManager();

        $builder = $this->getImageBuilder($config);

        $context = $builder->getContext();

        $this->copyFixtures($context);

        $params = ['t' => $this->getImageName()];

        try {
            $buildStream = $manager->build(
                $context->toStream(),
                $params,
                ImageManager::FETCH_OBJECT
            );

            return true;
        } catch (ClientErrorException $e) {

            return false;
        }
    }

    /**
     * Build the docker container
     *
     * @param OutputInterface $output
     * @param array $config
     *
     * @return boolean
     */
    protected function buildContainer(OutputInterface $output, array $config = [])
    {
        $manager = $this->getContainerManager();

        $config = $this->getContainerConfig($config);

        $params = ['name' => $this->getName()];

        try {
            $manager->create(
                $config,
                $params,
                ContainerManager::FETCH_OBJECT
            );

            return true;
        } catch (ClientErrorException $e) {

            return false;
        }
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
     * @param Context $context
     */
    protected function copyFixtures(Context $context)
    {
        // noop
    }

    /**
     * @return ImageManager
     */
    protected function getImageManager()
    {
        return $this->docker->getImageManager();
    }

    /**
     * @return ContainerManager
     */
    protected function getContainerManager()
    {
        return $this->docker->getContainerManager();
    }

    /**
     * Fetches the image if it exists
     *
     * @return Image|false
     */
    protected function getImage()
    {
        try {
            $image = $this->getImageManager()->find($this->getImageName());

            return $image instanceof Image;
        } catch (ClientErrorException $e) {
            return false;
        }
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
