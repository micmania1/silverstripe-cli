<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BaseCommand extends Command
{
	const COLUMN_LENGTH = 80;

	const CLI_FILE = '.silverstripe-cli.yaml';

	protected function configure()
	{
		$this->setName('base');
		$this->setHidden(true);
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$formatter = $this->getHelper('formatter');
		$logo = file_get_contents(CLI_ASSETS . '/ascii-logo');
		$block = $formatter->formatBlock($logo, 'info');
		$output->writeln($block);
	}

	/**
	 * @param string $directory
	 *
	 * @return boolean
	 */
	protected function isCliProject($directory)
	{
		return $this->hasCliFile($directory);
	}

	/**
	 * @return string
	 */
	protected function getCliFile()
	{
		return static::CLI_FILE;
	}

	/**
	 * @param string $directory
	 *
	 * @return array
	 */
	protected function hasCliFile($directory)
	{
		// If the silverstripe cli config file doesn't exist, then its not considered
		// a silverstripe project
		$cliFile = rtrim($directory, DIRECTORY_SEPARATOR)
			. DIRECTORY_SEPARATOR
			. $this->getCliFile();

		return file_exists($cliFile);
	}

}
