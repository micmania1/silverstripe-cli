<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvUp extends Command
{
	protected function configure()
	{
		$this->setName('env:up')
			->setDescription('Start up the environment');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeLn('Starting environment...');
	}
}
