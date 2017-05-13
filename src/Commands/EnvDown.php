<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvDown extends Command
{
	protected function configure()
	{
		$this->setName('env:down')
			->setDescription('Halt the environment');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeLn('Halting environment...');
	}
}
