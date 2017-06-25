<?php

namespace micmania1\SilverStripeCli\Commands;

use Docker\Docker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\RuntimeException;

use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Docker\Environment;
use micmania1\SilverStripeCli\Helpers\Spinner;

class EnvUp extends BaseCommand
{
	/**
	 * @var Environment
	 */
	protected $environment;

	protected function configure()
	{
		$this->setName('env:up')
			->setDescription('Start up the environment');
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		$project = new Project(getcwd());
		if(!$project->isCli()) {
			throw new \Exception('You must be in a SilverStripe Cli project to run this command');
		}

		$docker = new Docker();
		$this->environment = new Environment($project, $docker);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$environment = $this->getEnvironment();
        if(!$environment->build($output)) {
            throw new RuntimeException('Unable to build environment');
        }
		$environment->start($output);
	}

	/**
	 * @returns EnvironmentInterface
	 */
	protected function getEnvironment()
	{
		return $this->environment;
	}
}
