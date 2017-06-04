<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvStatus extends Command
{
	protected function configure()
	{
		$this->setName('env:status')
			->setDescription('Show the environment status');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeStatus('Current status', 'Running', 'info');
		$output->emptyLine();
	}
}
