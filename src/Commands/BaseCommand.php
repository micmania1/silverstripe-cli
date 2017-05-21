<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BaseCommand extends Command
{
	use CommandHeader;

	const COLUMN_LENGTH = 80;

	const CLI_FILE = '.silverstripe-cli.yaml';

	protected function configure()
	{
		$this->setName('base');
		$this->setHidden(true);
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$this->displayHeader($output);
	}

}
