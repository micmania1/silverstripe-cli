<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestPhpSpec extends Command
{
    protected function configure()
    {
        $this->setName('test:php:spec')
            ->setDescription('Run php spec tests');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    }
}
