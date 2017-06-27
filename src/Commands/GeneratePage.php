<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GeneratePage extends Command
{
    protected function configure()
    {
        $this->setName('generate:page')
            ->setDescription('Generate a SilverStripe page and controller');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn('Component created');
    }
}
