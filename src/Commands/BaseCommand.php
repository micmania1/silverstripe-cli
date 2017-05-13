<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BaseCommand extends Command
{
	const COLUMN_LENGTH = 50;

	protected function configure()
	{
		$this->setName('base');
		$this->setHidden(true);
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$output->writeln('<comment>' . str_pad('', self::COLUMN_LENGTH, '-') . '</comment>');
		$output->writeln('<comment> SilverStripe CLI</comment>');
		$output->writeln('<comment>' . str_pad('', self::COLUMN_LENGTH, '-') . '</comment>');
	}

	protected function clearLine($output) 
	{
		// Move the cursor to the beginning of the line
		$output->write("\x0D");

		// Erase the line
		$output->write("\x1B[2K");
	}

	protected function spin($message, $process, $output) 
	{
		$state = 0;
		while($process->isRunning()) {
			$state++;

			$this->clearLine($output);

			// Write the message
			$output->write(str_pad($message, self::COLUMN_LENGTH - 1));

			// Figure out our spinner state
			switch($state) {
				case 1:
					$output->write('|');
					break;
				case 2:
					$output->write('/');
					break;
				case 3:
					$output->write('-');
					break;
				case 4:
					$output->write('\\');
					$state = 0;
					break;
			}

			usleep(80000);
		}

		$this->clearLine($output);


		if($process->isSuccessful()) {
			$output->write(str_pad($message, self::COLUMN_LENGTH - 2));
			$output->writeln('<info>OK</info>');
		} else {
			$output->write(str_pad($message, self::COLUMN_LENGTH - 4));
			$output->writeln('<error>FAIL</error>');
		}
	}
}
