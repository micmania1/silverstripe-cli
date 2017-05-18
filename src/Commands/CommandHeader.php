<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Output\OutputInterface;

trait CommandHeader
{
	protected function displayHeader(OutputInterface $output)
	{
		$formatter = $this->getHelper('formatter');
		$logo = file_get_contents(CLI_ASSETS . '/ascii-logo');
		$block = $formatter->formatBlock($logo, 'info');
		$output->writeln($block);
	}

	abstract public function getHelper($name);
}
