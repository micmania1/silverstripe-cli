<?php

namespace micmania1\SilverStripeCli\Commands;

use Docker\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use micmania1\SilverStripeCli\ServiceInterface;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Docker\Environment;

class EnvStatus extends BaseCommand
{
    /**
     * @var Environment
     */
    protected $environment;

    protected function configure()
    {
        $this->setName('env:status')
            ->setDescription('Show the environment status');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $project = new Project(getcwd());
        if (!$project->isCli()) {
            throw new \Exception('You must be in a SilverStripe Cli project to run this command');
        }

        $docker = new Docker();
        $this->environment = new Environment($project, $docker);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->environment->status($output);
    }
}
