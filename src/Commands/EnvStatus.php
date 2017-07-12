<?php

namespace micmania1\SilverStripeCli\Commands;

use Docker\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use micmania1\SilverStripeCli\Console\OutputInterface;
use micmania1\SilverStripeCli\ServiceInterface;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Docker\Environment;

class EnvStatus extends BaseCommand
{
    /**
     * @var Environment
     */
    protected $environment;

    public function __construct(Environment $environment)
    {
        parent::__construct();

        $this->environment = $environment;
    }

    protected function configure()
    {
        $this->setName('env:status')
            ->setDescription('Show the environment status');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->environment->status($output);
    }
}
