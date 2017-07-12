<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use micmania1\SilverStripeCli\Console\OutputInterface;

class GenerateTheme extends Command
{
    protected function configure()
    {
        $this->setName('generate:theme')
            ->setDescription('Generate a SilverStripe theme with build tools ready to go.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn('Theme created...');
    }
}
