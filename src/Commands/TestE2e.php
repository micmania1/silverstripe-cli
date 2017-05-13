<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestE2e extends Command
{
	protected function configure()
	{
		$this->setName('test:e2e')
			->setDescription('Run e2e tests');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
	}
}
