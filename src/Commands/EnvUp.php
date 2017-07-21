<?php

namespace micmania1\SilverStripeCli\Commands;

use Docker\Docker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use micmania1\SilverStripeCli\Console\OutputInterface;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\EnvironmentInterface;
use micmania1\SilverStripeCli\Helpers\Spinner;

class EnvUp extends BaseCommand
{
    /**
     * @var EnvironmentInterface
     */
    protected $environment;

    public function __construct(EnvironmentInterface $environment)
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
        if (!$this->environment->build($output)) {
            throw new RuntimeException('Unable to build environment');
        }

        if ($this->environment->start($output)) {
            $output->writeln(sprintf(
                " You can view the website at <info>%s</info>",
                $this->environment->getUrl()
            ));
            $output->emptyLine();
        }
    }
}
