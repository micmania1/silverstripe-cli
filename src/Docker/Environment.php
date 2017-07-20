<?php

namespace micmania1\SilverStripeCli\Docker;

use Closure;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use RandomLib\Generator;
use micmania1\SilverStripeCli\Docker\ServiceInterface;
use micmania1\SilverStripeCli\EnvironmentInterface;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Commands\BaseCommand;
use micmania1\SilverStripeCli\Console\OutputInterface;

class Environment implements EnvironmentInterface
{
    /**
     * @var Project
     */
    protected $project;

    /**
     * @var Generator
     */
    protected $generator;

    /**
     * @var MariaDbService
     */
    protected $dbService;

    /**
     * @var WebService
     */
    protected $webService;

    /**
     * @param Project $project
     * @param Generator $generator
     * @param MariaDbService $dbService
     * @param WebService $webService
     */
    public function __construct(
        Project $project,
        Generator $generator,
        MariaDbService $dbService,
        WebService $webService
    ) {
        $this->project = $project;
        $this->generator = $generator;
        $this->dbService = $dbService;
        $this->webService = $webService;
    }

    /**
     * {@inheritdoc}
     */
    public function build(OutputInterface $output)
    {
        if (!$this->dbService->containerExists()
            && !$this->buildDatabaseService($output)
        ) {
            return false;
        }

        if (!$this->webService->containerExists()
            && !$this->buildWebService($output)
        ) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function status(OutputInterface $output)
    {
        $dbIsRunning = $this->dbService->isRunning($output);
        $webIsRunning = $this->webService->isRunning($output);

        $message = 'Environment status';
        if (!$dbIsRunning || !$webIsRunning) {
            $output->writeStatus($message, 'STOPPED', 'warning');
            $output->emptyLine();

            return false;
        }

        $output->writeStatus($message, 'RUNNING', 'success');
        $output->emptyLine();
        $output->emptyLine();

        $webVars = $this->webService->getEnvVars();
        $dbVars = $this->dbService->getEnvVars();
        $dotEnv = $this->project->getDotEnv();

        $vars = array_merge($dotEnv, [
            'SS_DATABASE_NAME' => $webVars['SS_DATABASE_NAME'],
            'SS_DATABASE_USERNAME' => $webVars['SS_DATABASE_USERNAME'],
            'SS_DATABASE_PASSWORD' => $webVars['SS_DATABASE_PASSWORD'],
            'DB_HOSTNAME' => '127.0.0.1',
            'DB_PORT' => $dbVars['SSCLI_HOST_PORT'],
            'WEB_PORT' => $webVars['SSCLI_HOST_PORT'],
        ]);

        $output->displayEnvironmentDetails($vars);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function start(OutputInterface $output)
    {
        $output->writeStatus(sprintf(
            'Starting environment <info>%s</info>',
            $this->project->getName()
        ));


        $dbRunning = $this->dbService->isRunning($output);
        $webRunning = $this->webService->isRunning($output);

        if ($dbRunning && $webRunning) {
            $output->clearLine();
            $output->writeStatus(
                sprintf('Starting environment %s', $this->project->getName()),
                'ALREADY RUNNING',
                'warning'
            );
            $output->emptyLine();
            $output->emptyLine();

            return true;
        }

        // We need to start the db in order to obtain an ip address
        if (!$dbRunning && !$this->dbService->start($output)) {
            $output->clearLine();
            $output->writeStatus(
                sprintf('Starting environment %s', $this->project->getName()),
                'FAIL',
                'error'
            );
            $output->emptyLine();
            $output->emptyLine();

            return false;
        }

        $dbVars = $this->dbService->getEnvVars();
        $webVars = $this->webService->getEnvVars();

        $config = [
            'dbIp' => $this->dbService->getIp(),
            'dbRootUser' => 'root',
            'dbRootPassword' => $dbVars['MYSQL_ROOT_PASSWORD'],
            'dbPort' => $dbVars['SSCLI_HOST_PORT'],
            'dbName' => $webVars['SS_DATABASE_NAME'],
            'dbUser' => $webVars['SS_DATABASE_USERNAME'],
            'dbPassword' => $webVars['SS_DATABASE_PASSWORD'],
        ];

        // Ensure the web instance db details are set in MySQL
        $this->dbService->ensureDatabaseExists($config);

        // Start the web service
        if ($this->webService->start($output, $config)) {
            $output->clearLine();
            $output->writeStatus(
                sprintf('Starting environment %s', $this->project->getName()),
                'OK',
                'success'
            );
            $output->emptyLine();
            $output->emptyLine();

            return true;
        }
        $output->writeln('ohh');

        $output->clearLine();
        $output->writeStatus(
            sprintf('Starting environment %s', $this->project->getName()),
            'FAIL',
            'error'
        );
        $output->emptyLine();
        $output->emptyLine();

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function stop(OutputInterface $output)
    {
        $message = sprintf('Stopping environment %s', $this->project->getName());
        $output->writeStatus($message);

        $dbStop = $this->dbService->stop($output);
        $webStop = $this->webService->stop($output);

        $output->clearLine();

        if ($webStop && $dbStop) {
            $output->writeStatus($message, 'STOPPED', 'info');
            $output->emptyLine();

            return true;
        }

        $output->writeStatus($message, 'FAIL', 'error');
        $output->emptyLine();

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function export(OutputInterface $output, $outputFile)
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function import(OutputInterface $output, $inputFile)
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl()
    {
        $webVars = $this->webService->getEnvVars();

        return 'http://localhost:' . $webVars['SSCLI_HOST_PORT'];
    }

    /**
     * Responsible for creating config for the database service and building it
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    protected function buildDatabaseService(OutputInterface $output)
    {
        $config = [
            'rootPass' => $this->generator->generateString(32),
            'hostPort' => (string) $this->generator->generateInt(9000, 9999),
        ];

        $message = 'Building <info>database</info> service';
        $output->writeStatus($message);

        $built = $this->dbService->build($output, $config);

        $output->clearLine();
        if ($built) {
            $output->writeStatus($message, 'OK', 'success');
        } else {
            $output->writeStatus($message, 'FAIL', 'error');
        }
        $output->emptyLine();

        return $built;
    }

    /**
     * Responsible for creating config for the database service and building it
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    protected function buildWebService(OutputInterface $output)
    {
        $config = [
            'cliId' => $this->generator->generateString(6),
            'hostPort' => (string) $this->generator->generateInt(8000, 8999),
            'dbHost' => 'database',
            'dbUser' => $this->project->getName(),
            'dbPassword' => $this->generator->generateString(32),
            'dbPort' => 3306,
            'dbName' => $this->project->getName(),
            'hostDir' => $this->project->getRootDirectory(),
        ];

        $message = 'Building <info>web</info> service';
        $output->writeStatus($message);

        $built = $this->webService->build($output, $config);

        $output->clearLine();
        if ($built) {
            $output->writeStatus($message, 'OK', 'success');
        } else {
            $output->writeStatus($message, 'FAIL', 'error');
        }
        $output->emptyLine();

        return $built;
    }
}
