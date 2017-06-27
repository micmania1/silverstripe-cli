<?php

namespace micmania1\SilverStripeCli\Commands;

use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HelpCommand extends ListCommand
{
    use CommandHeader;

    protected function configure()
    {
        parent::configure();
        $this->setName('silverstripe');
        $this->setHidden(true);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->displayHeader($output);
        parent::initialize($input, $output);
    }
}
