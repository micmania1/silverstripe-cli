<?php

namespace micmania1\SilverStripeCli\Commands;

use Docker\Docker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\RuntimeException;

use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Docker\Environment;
use micmania1\SilverStripeCli\Helpers\Spinner;

class EnvUp extends BaseCommand
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
        $this->setName('env:up')
            ->setDescription('Start up the environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->environment->build($output);
        $this->environment->start($output);
    }
}
