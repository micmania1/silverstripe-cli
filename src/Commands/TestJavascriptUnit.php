<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestJavascriptUnit extends Command
{
	protected function configure()
	{
		$this->setName('test:js:unit')
			->setDescription('Run javascript unite tests');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
	}
}
