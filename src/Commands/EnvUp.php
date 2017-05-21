<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\EnvironmentProviders\Provider;
use micmania1\SilverStripeCli\EnvironmentProviders\Docker;
use micmania1\SilverStripeCli\Helpers\Spinner;

class EnvUp extends BaseCommand
{
	/**
	 * The MAJOR version of the base image
	 *
	 * @const string
	 */
	const IMAGE_VERSION = 'zero';

	protected function configure()
	{
		$this->setName('env:up')
			->setDescription('Start up the environment');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$spinner = new Spinner($output, 'Building environment');
		$this->getProvider()->build(function($info) use ($spinner) {
			// echo $info->getStream();
			$spinner->spin();
		});
		$spinner->clearLine();
		$spinner->updateStatus('OK', 'info');
		$output->writeln('');

		$spinner = new Spinner($output, 'Starting environment');
		$this->getProvider()->up(function() use ($spinner) {
			//$spinner->spin();
		});
		$spinner->clearLine();
		$spinner->updateStatus('OK', 'info');
		$output->writeln('');

		$output->writeln('<info>Access your site at http://localhost:8080');
	}

	/**
	 * @returns Provider
	 */
	protected function getProvider()
	{
		$project = new Project(getcwd());
		if(!$project->isCli()) {
			throw new \Exception('You must be in a SilverStripe Cli project to run this command');
		}

		// @todo switch this out with container to resolve provider correctly
		return new Docker($project);
	}
}
