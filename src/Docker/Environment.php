<?php

namespace micmania1\SilverStripeCli\Docker;

use Closure;
use Exception;
use PDO;
use PDOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use RandomLib\Generator;
use micmania1\SilverStripeCli\ServiceInterface;
use micmania1\SilverStripeCli\EnvironmentInterface;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Commands\BaseCommand;

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
     * Builds our base docker image (or images)
     */
    public function build(OutputInterface $output)
    {
        return $this->buildDatabaseService($output)
            && $this->buildWebService($output);
    }

    public function status(OutputInterface $output)
    {
        $this->dbService->status($output);
        $this->webService->status($output);

        $output->emptyLine();

        $this->displayWebsiteDetails($output);
        $this->displayDatabaseDetails($output);
    }

    public function start(OutputInterface $output)
    {
        // We need to start the db in order to obtain an ip address
        $this->dbService->start($output);

        $dbVars = $this->dbService->getEnvVars();
        $webVars = $this->webService->getEnvVars();

        $config = [
            'dbIp' => $this->dbService->getIp(),
            'dbRootUser' => 'root',
            'dbRootPassword' => $dbVars['MYSQL_ROOT_PASSWORD'],
            'dbPort' => 9000,
            'dbName' => $webVars['SS_DATABASE_NAME'],
            'dbUser' => $webVars['SS_DATABASE_USERNAME'],
            'dbPassword' => $webVars['SS_DATABASE_PASSWORD'],
        ];

        // Ensure the web instance db details are set in MySQL
        $this->ensureDatabaseExists($config);

        // Start the web service
        $this->webService->start($output, $config);
    }

    public function stop(OutputInterface $output)
    {
        $this->dbService->stop($output);
        $this->webService->stop($output);
    }

    public function export()
    {
        throw new Exception('Not implemented');
    }

    public function import($file)
    {
        throw new Exception('Not implemented');
    }

    protected function buildDatabaseService(OutputInterface $output)
    {
        $password = $this->generator->generateString(32);

        $config = ['rootPass' => $password];

        $this->dbService->build($output, $config);

        return true;
    }

    protected function buildWebService(OutputInterface $output)
    {
        $vars = $this->dbService->getEnvVars();
        if (!isset($vars['MYSQL_ROOT_PASSWORD'])) {
            throw new RuntimeException('MYSQL_ROOT_PASSWORD is missing');
        }

        $config = [
            'cliId' => $this->generator->generateString(6),
            'hostPort' => (string) $this->generator->generateInt(8000, 8999),
            'dbHost' => 'database',
            'dbUser' => $this->getProject()->getName(),
            'dbPassword' => $this->generator->generateString(32),
            'dbPort' => 3306,
            'dbName' => $this->getProject()->getName(),
            'hostDir' => $this->getProject()->getRootDirectory(),
        ];

        $this->webService->build($output, $config);

        return true;
    }

    protected function getProject()
    {
        return $this->project;
    }

    private function ensureDatabaseExists(array $config)
    {
        $query = sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $config['dbName']
        );

        $conn = $this->getDbConnection($config);
        $result = $conn->query($query);

        $query = sprintf(
            "GRANT ALL PRIVILEGES ON %s.* to %s@'%%' IDENTIFIED BY %s",
            $config['dbName'],
            $conn->quote($config['dbUser']),
            $conn->quote($config['dbPassword'])
        );
        $result = $conn->query($query);

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

            // We've exceeded are remaining tries
            throw $e;
        }
    }

    protected function displayWebsiteDetails(OutputInterface $output)
    {
        $env = $this->webService->getEnvVars();
        $dotEnv = $this->getProject()->getDotEnv();

        if (isset($dotEnv['SS_DEFAULT_ADMIN_USERNAME'], $dotEnv['SS_DEFAULT_ADMIN_PASSWORD'])) {
            $adminUsername = $dotEnv['SS_DEFAULT_ADMIN_USERNAME'];
            $adminPassword = $dotEnv['SS_DEFAULT_ADMIN_PASSWORD'];
        } else {
            $adminUsername = '<warning>No username</warning>';
            $adminPassword = '<warning>No password</warning>';
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
        $output->writeln('');
    }

    protected function displayDatabaseDetails(OutputInterface $output)
    {
        $env = $this->webService->getEnvVars();
        $table = new Table($output);
        $table->setHeaders([new TableCell('Database Access', ['colspan' => 2])]);
        $table->setStyle('compact');
        $table->setRows([
            ['Database name', $env['SS_DATABASE_NAME']],
            ['Username', $env['SS_DATABASE_USERNAME']],
            ['Password', $env['SS_DATABASE_PASSWORD']],
            ['Host', 'localhost'],
            ['Port', '9000'],
        ]);
        $table->setColumnWidth(0, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->setColumnWidth(1, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->render();
        $output->writeln('');
    }
}
