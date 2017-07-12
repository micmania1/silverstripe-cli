<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use micmania1\SilverStripeCli\Console\OutputInterface;

class GenerateController extends Command
{
    protected function configure()
    {
        $this->setName('generate:controller')
            ->setDescription('Generate a SilverStripe controller');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn('Component created');
    }
}
