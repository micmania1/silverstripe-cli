<?php

namespace micmania1\SilverStripeCli\Docker;

use PDO;
use PDOException;
use Docker\Context\ContextBuilder;
use Docker\API\Model\ContainerConfig;
use Docker\API\Model\PortBinding;
use Docker\API\Model\HostConfig;
use Symfony\Component\Console\Exception\RuntimeException;

class MariaDbService extends AbstractService
{
    /**
     * {@inheritdoc}
     */
    public function getImageName()
    {
        return 'sscli-db:1';
    }

    /**
     * This ensures the database exists with the web instances user
     *
     * @param array $config
     *
     * @throws RuntimeException
     */
    public function ensureDatabaseExists(array $config)
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
     * {@inheritdoc}
     */
    protected function getImageBuilder(array $config = [])
    {
        $builder = new ContextBuilder();
        $builder->from('mariadb:10.1');

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerConfig(array $config = [])
    {
        if (!isset($config['rootPass'])) {
            throw new RuntimeException('rootPass config missing');
        }

        if (!isset($config['hostPort'])) {
            throw new RuntimeException('hostPort config missing');
        }

        $containerConfig = new ContainerConfig();
        $containerConfig->setImage($this->getImageName());
        $containerConfig->setTty(true);
        $containerConfig->setEnv([
            sprintf('SSCLI_HOST_PORT=%d', $config['hostPort']),
            sprintf('MYSQL_ROOT_PASSWORD=%s', $config['rootPass']),
        ]);
        $containerConfig->setExposedPorts(['3306/tcp' => (object) []]);

        // Map ports
        $portBinding = new PortBinding();
        $portBinding->setHostPort($config['hostPort']);
        $portBinding->setHostIp('0.0.0.0');
        $map = new \ArrayObject();
        $map['3306/tcp'] = [$portBinding];

        $hostConfig = new HostConfig();
        $hostConfig->setPortBindings($map);

        $containerConfig->setHostConfig($hostConfig);

        return $containerConfig;
    }
}
