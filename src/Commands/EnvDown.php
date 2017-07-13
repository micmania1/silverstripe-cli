<?php

namespace micmania1\SilverStripeCli\Commands;

use Docker\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use micmania1\SilverStripeCli\Console\OutputInterface;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Docker\Environment;

class EnvDown extends BaseCommand
{
    /**
     * @var Environment
     */
    protected $environment;

    public function __construct(Environment $environemnt)
    {
        parent::__construct();

        $this->environment = $environemnt;
    }

    protected function configure()
    {
        $this->setName('env:down')
            ->setDescription('Halt the environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->environment->stop($output);

        $output->emptyLine();
    }
}
