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
use Symfony\Component\Console\Output\OutputInterface;

use micmania1\SilverStripeCli\ServiceInterface;
use micmania1\SilverStripeCli\Helpers\Spinner;
use micmania1\SilverStripeCli\Model\Project;

abstract class AbstractService implements ServiceInterface
{
	/**
	 * @var Project
	 */
	protected $project;

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
	abstract protected function getImageBuilder();

	/**
	 * @return Docker\Manager\ContainerConfig
	 */
	abstract protected function getContainerConfig();

	/**
	 * @return string
	 */
	abstract protected function getImageName();

	/**
	 * @param Project $project
	 * @param string $name
	 * @param Docker $docker
	 */
	public function __construct(Project $project, $name, Docker $docker)
	{
		$this->project = $project;
		$this->name = $name;
		$this->docker = $docker;
	}

	public function getProject()
	{
		return $this->project;
	}

	public function build(OutputInterface $output)
	{
		if(!$this->imageExists()) {
			$this->buildImage($output);
		}

		if(!$this->containerExists() && $this->imageExists()) {
			$this->buildContainer($output);
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
		if(!$container) {
			$output->emptyLine();
			$output->writeln(' The environment is not ready. Run env:up');
			$output->emptyLine();
			return;
		}

		$state = $container->getState();

		if ($state->getPaused()) {
			$status = ServiceInterface::STATUS_PAUSED;
			$type = 'warning';
		} else if ($state->getRestarting()) {
			$status = ServiceInterface::STATUS_RESTARTING;
			$type = 'warning';
		} else if ($state->getRunning()) {
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
        if(!$container) {
            throw new RuntimeException('Containier does not exist');
        }

        $config = $container->getConfig()->getNetworkingConfig();

        if(!isset($config['IPAddress'])) {
            throw new RuntimeException('Unable to obtain database ip address');
        }

        return $config['IPAddress'];
    }

    protected function exec(OutputInterface $output, $cmd)
    {
        $execManager = $this->docker->getExecManager();

        $execConfig = new ExecConfig();

        $cmd = explode(' ', $cmd);
        $execConfig->setCmd($cmd);

        $id = $this->getContainer()->getId();
        $response = $execManager->create($id, $execConfig);

        $startConfig = new ExecStartConfig();
        $startConfig->setDetach(true);
        $execManager->start($response->getId(), $startConfig);

    }

	protected function buildImage(OutputInterface $output)
	{
		$manager = $this->getImageManager();

		$builder = $this->getImageBuilder();

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
			while(!$stream->eof()) {
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

	protected function buildContainer(OutputInterface $output)
	{
		$manager = $this->getContainerManager();
		$config = $this->getContainerConfig();

		$params = ['name' => $this->getName()];
		$response = $manager->create(
			$config,
			$params,
			ContainerManager::FETCH_STREAM
		);
		$stream = $response->getBody();

		$message = 'Building your container';
		$spinner = new Spinner($output, $message);
		while(!$stream->eof()) {
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

	protected function copyFixtures(Context $context) { }

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

	/**
	 * Returns an array of environment variables
	 *
	 * @return array
	 */
	protected function getEnvVars()
	{
		$container = $this->getContainer();
		if(!$container) {
			return [];
		}

		$raw = $container->getConfig()->getEnv();
		$vars = [];
		foreach($raw as $var) {
			$split = explode('=', $var, 2);
			$vars[$split[0]] = $split[1];
		}

		return $vars;
	}
}
