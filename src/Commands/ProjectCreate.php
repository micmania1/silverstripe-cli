<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ProjectCreate extends BaseCommand
{
	/**
	 * @var string
	 */
	protected $projectName;

	protected function configure()
	{
		// Set name and descriptions
		$this->setName('new')
			->setDescription('Create a new SilverStripe project')
			->setHelp('This will create a skeleton SilverStripe app and initialise its git repo');

		// Add command arguments
		$this->addArgument('name', InputArgument::REQUIRED, 'The directory of the project');

		// Add command options
		$this->addOption('exclude-git', null, InputOption::VALUE_NONE, 'Don\'t initialise a git repo');
	}

	/**
	 * Perform any validation here and assign variables
	 *
	 * {@inheritdoc}
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		$this->projectName = $input->getArgument('name');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$command = sprintf(
			'%s create-project silverstripe/installer %s',
			COMPOSER_BIN,
			$this->projectName
		);
		$process = new Process($command);
		$process->start();

		while($process->isRunning()) {
			$this->spin('test', $process, $output);
		}

		if(!$process->isSuccessful()) {
			throw new ProcessFailedException($process);
		}

		$output->writeln('<info>Project created</info>');
	}

	/**
	 * Returns the name of the project
	 *
	 * @return string
	 */
	protected function getProjectName()
	{
		return $this->projectName;
	}
}
