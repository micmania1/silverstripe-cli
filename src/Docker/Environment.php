<?php

namespace micmania1\SilverStripeCli\Docker;

use Closure;
use PDO;
use PDOException;
use Symfony\Component\Filesystem\Filesystem;
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
        return $this->buildDatabaseService($output)
            && $this->buildWebService($output);
    }

    /**
     * {@inheritdoc}
     */
    public function status(OutputInterface $output)
    {
        $dbIsRunning = $this->dbService->isRunning($output);
        $webIsRunning = $this->webService->isRunning($output);

        $message = 'Environment status';
        if ($dbIsRunning && $webIsRunning) {
            $output->writeStatus($message, 'RUNNING', 'success');
        } else {
            $output->writeStatus($message, 'STOPPED', 'warning');
        }
        $output->emptyLine();
        $output->emptyLine();

        $this->displayWebsiteDetails($output);
        $this->displayDatabaseDetails($output);
    }

    /**
     * {@inheritdoc}
     */
    public function start(OutputInterface $output)
    {
        $output->writeStatus(sprintf(
            'Starting environment %s',
            $this->project->getName()
        ));


        $dbRunning = $this->dbService->isRunning($output);
        $webRunning = $this->webService->isRunning($output);

        if($dbRunning && $webRunning) {
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

            $output->writeln('ohh');
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
        $this->ensureDatabaseExists($config);

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
     * Responsible for creating config for the database service and building it
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    protected function buildDatabaseService(OutputInterface $output)
    {
        $password = $this->generator->generateString(32);

        $config = [
            'rootPass' => $password,
            'hostPort' => (string) $this->generator->generateInt(9000, 9999),
        ];

        // @todo check returns value so we can say whether the service has been built
        $this->dbService->build($output, $config);

        return true;
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

        // @todo check returns value so we can say whether the service has been built
        $this->webService->build($output, $config);

        return true;
    }

    /**
     * This ensures the database exists with the web instances user
     *
     * @param array $config
     *
     * @throws RuntimeException
     */
    protected function ensureDatabaseExists(array $config)
    {
        $query = sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 '
                . 'COLLATE utf8mb4_unicode_ci',
            $config['dbName']
        );

        $conn = $this->getDbConnection($config);
        if (!$conn->query($query)) {
            throw new RuntimeException('Unable to create database.');
        }

        $query = sprintf(
            "GRANT ALL PRIVILEGES ON %s.* to %s@'%%' IDENTIFIED BY %s",
            $config['dbName'],
            $conn->quote($config['dbUser']),
            $conn->quote($config['dbPassword'])
        );
        if (!$conn->query($query)) {
            throw new RuntimeException('Unable to create database user.');
        }
    }

    /**
     * Fetches the connection to the database instance. This has retry functionality
     * as for some reason, the timeout for mysql connection is being ignored. When
     * the DB instance spins up, it some times takes a while for MySQL to be ready
     * so we should expect a few failures when trying to connect.
     *
     * @param array $config
     * @param int $triesRemaining The number of times it will try to connect
     *
     * @return PDO
     */
    private function getDbConnection(array $config, $triesRemaining = 10)
    {
        try {
            $options = [
                PDO::ATTR_TIMEOUT => 120,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ];

            // Even though its throwing and exception, it also writes an error out
            // to the console.
            return @new PDO(
                sprintf('mysql:host=0.0.0.0;port=%dcharset=utf8mb4', $config['dbPort']),
                $config['dbRootUser'],
                $config['dbRootPassword'],
                $options
            );
        } catch (PDOException $e) {
            if ($triesRemaining > 0) {
                sleep(3);
                return $this->getDbConnection($config, $triesRemaining - 1);
            }

            // We've exceeded our remaining tries
            throw $e;
        }
    }

    /**
     * Displays details for the web service including url and default cms admin
     *
     * @param OutputInterface $output
     */
    protected function displayWebsiteDetails(OutputInterface $output)
    {
        $env = $this->webService->getEnvVars();
        $dotEnv = $this->project->getDotEnv();

        if (isset($dotEnv['SS_DEFAULT_ADMIN_USERNAME'], $dotEnv['SS_DEFAULT_ADMIN_PASSWORD'])) {
            $adminUsername = $dotEnv['SS_DEFAULT_ADMIN_USERNAME'];
            $adminPassword = $dotEnv['SS_DEFAULT_ADMIN_PASSWORD'];
        } else {
            $adminUsername = '<warning>No default admin</warning>';
            $adminPassword = '<warning>No default admin</warning>';
        }

        $table = new Table($output);
        $table->setHeaders([new TableCell('Website Access', ['colspan' => 2])]);
        $table->setStyle('compact');
        $table->setRows([
            ['URL', sprintf('http://localhost:%d', $env['SSCLI_HOST_PORT'])],
            ['Admin URL', sprintf('http://localhost:%d/admin', $env['SSCLI_HOST_PORT'])],
            ['CMS Admin', $adminUsername],
            ['CMS Password', $adminPassword],
        ]);
        $table->setColumnWidth(0, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->setColumnWidth(1, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->render();

        $output->emptyLine();
    }

    /**
     * Displays details for the database service
     *
     * @param OutputInterface $output
     */
    protected function displayDatabaseDetails(OutputInterface $output)
    {
        $env = $this->webService->getEnvVars();
        $dbEnv = $this->dbService->getEnvVars();

        $table = new Table($output);
        $table->setHeaders([new TableCell('Database Access', ['colspan' => 2])]);
        $table->setStyle('compact');
        $table->setRows([
            ['Database name', $env['SS_DATABASE_NAME']],
            ['Username', $env['SS_DATABASE_USERNAME']],
            ['Password', $env['SS_DATABASE_PASSWORD']],
            ['Host', '127.0.0.1'],
            ['Port', $dbEnv['SSCLI_HOST_PORT']],
        ]);
        $table->setColumnWidth(0, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->setColumnWidth(1, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->render();

        $output->emptyLine();
    }
}
