<?php

namespace micmania1\SilverStripeCli\Commands;

use Docker\Docker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;

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
		$environment->build($output);
		$environment->start($output);

		$table = new Table($output);
		$table->setHeaders([new TableCell('Website Access', ['colspan' => 2])]);
		$table->setStyle('compact');
		$table->setRows([
			['URL', 'http://localhost:8080'],
			['Admin URL', 'http://localhost:8080/admin'],
			['CMS Admin', 'admin'],
			['CMS Password', 'password']
		]);
		$table->setColumnWidth(0, ceil(BaseCommand::COLUMN_LENGTH / 2));
		$table->setColumnWidth(1, ceil(BaseCommand::COLUMN_LENGTH / 2));
		$table->render();
		$output->writeln('');

		$table = new Table($output);
		$table->setHeaders([new TableCell('Database Access', ['colspan' => 2])]);
		$table->setStyle('compact');
		$table->setRows([
			['Database name', 'dbname'],
			['Username', 'someuser'],
			['Password', 'somepassword'],
			['Host', 'localhost'],
			['Port', '3306'],
		]);
		$table->setColumnWidth(0, ceil(BaseCommand::COLUMN_LENGTH / 2));
		$table->setColumnWidth(1, ceil(BaseCommand::COLUMN_LENGTH / 2));
		$table->render();
		$output->writeln('');
	}

	/**
	 * @returns EnvironmentInterface
	 */
	protected function getEnvironment()
	{
		return $this->environment;
	}
}
