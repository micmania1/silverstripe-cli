<?php

namespace micmania1\SilverStripeCli\Docker;

use Closure;
use Exception;

use Docker\Docker;
use Docker\Context\ContextBuilder;
use Docker\Manager\ImageManager;
use Docker\API\Model\Buildinfo;
use Docker\API\Model\ContainerConfig;
use Docker\API\Model\HostConfig;
use Docker\API\Model\PortBinding;
use Docker\Manager\ContainerManager;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;

use micmania1\SilverStripeCli\ServiceInterface;
use micmania1\SilverStripeCli\EnvironmentInterface;
use micmania1\SilverStripeCli\Model\Project;

class Environment implements EnvironmentInterface
{
	/**
	 * @var Project
	 */
	protected $project;

	/**
	 * @var DockerService
	 */
	protected $docker;

	/**
	 * @var micmania1\SilverStripeCli\ServiceInterface[]
	 */
	protected $services = [];

	/**
	 * @param Project $project
	 */
	public function __construct(Project $project, Docker $docker)
	{
		$this->project = $project;

		$name = $this->getProject()->getName();
		$dbName = 'database-shared';
		$webName = $name . '-web';

		$this->addService('db', new MariaDbService($project, $dbName, $docker));
		$this->addService('web', new WebService($project, $webName, $docker));
	}

	public function addService($name, ServiceInterface $service)
	{
		$this->services[$name] = $service;
	}

	public function getService($name)
	{
		return $this->services[$name];
	}

	/**
	 * Builds our base docker image (or images)
	 */
	public function build(OutputInterface $output)
	{
		$db = $this->getService('db');
		$built = $db->build($output);
		if(!$built) {
			throw new RuntimeException('Unable to build database service');
		}

		$web = $this->getService('web');
		$web->setConfig([
			'dbHost' => 'database',
			'dbUser' => 'root',
			'dbPassword' => 'rootpass',
			'dbPort' => 3306,
			'dbName' => 'mysite',
		]);

		return $web->build($output);
	}

	public function status(OutputInterface $output)
	{
		$this->getService('db')->status($output);
		$this->getService('web')->status($output);
	}

	public function start(OutputInterface $output)
	{
		$db = $this->getService('db');
		$db->start($output);

		$this->getService('web')->start(
			$output,
			['databaseIp' => $db->getIp()]
		);
	}

	public function stop(OutputInterface $output)
	{
		$this->getService('db')->stop($output);
		$this->getService('web')->stop($output);
	}

	public function export()
	{
		throw new Exception('Not implemented');
	}

	public function import($file)
	{
		throw new Exception('Not implemented');
	}

	protected function getProject()
	{
		return $this->project;
	}
}
