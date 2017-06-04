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
	 * @return Docker\Context\ContextBuilder
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
	}

	public function exists()
	{
		return $this->imageExists() && $this->containerExists();
	}

	public function status()
	{
		if(!$this->exists()) {
			return ServiceInterface::STATUS_NOT_READY;
		}

		$container = $this->getContainerManager()->find($this->getName());
		$state = $container->getState();

		if ($state->getPaused()) {
			return ServiceInterface::STATUS_PAUSED;
		} else if ($state->getRestarting()) {
			return ServiceInterface::STATUS_RESTARTING;
		} else if ($state->getRunning()) {
			return ServiceInterface::STATUS_RUNNING;
		}

		return ServiceInterface::STATUS_STOPPED;
	}

	public function start(OutputInterface $output)
	{
		$output->writeStatus('Starting environment');
		try {
			$this->getContainerManager()->start($this->getName());

			$output->clearLine();
			$output->writeStatus('Starting environment', 'OK', 'success');
			$output->emptyLine();
		} catch (ClientErrorException $e) {
			$output->clearLine();
			$output->writeStatus('Starting environment', 'FAIL', 'error');
			$output->emptyLine();

			throw $e;
		}
	}

	public function stop(OutputInterface $output)
	{
		$output->writeStatus('Stopping environment');
		try {
			$this->getContainerManager()->stop($this->getName());

			$output->clearLine();
			$output->writeStatus('Stopping environment', 'OK', 'success');
			$output->emptyLine();
		} catch (ClientErrorException $e) {
			$output->clearLine();
			$output->writeStatus('Stopping environment', 'FAIL', 'error');
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
					$stream->getContents(), 
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
				$stream->getContents(), 
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
		try {
			$container = $this->getContainerManager()->find($this->getName());

			return $container instanceof Container;
		} catch (ClientErrorException $e) {
			return false;
		}
	}
}
