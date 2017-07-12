<?php

namespace micmania1\SilverStripeCli\Docker;

use Docker\Context\ContextBuilder;
use Docker\API\Model\ContainerConfig;
use Docker\API\Model\PortBinding;
use Docker\API\Model\HostConfig;
use Docker\Context\Context;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Exception\RuntimeException;
use micmania1\SilverStripeCli\Commands\BaseCommand;
use micmania1\SilverStripeCli\Console\OutputInterface;

class MariaDbService extends AbstractService
{
    public function getImageName()
    {
        return 'sscli-db:1';
    }

    /**
     * {@inheritdoc}
     */
    protected function getImageBuilder($config = [])
    {
        $builder = new ContextBuilder();
        $builder->from('mariadb:10.1');

        return $builder;
    }

    protected function getContainerConfig($config = [])
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
        $containerConfig->setExposedPorts(['3306/tcp' => (object)[]]);

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

    protected function copyFixtures(Context $context)
    {
        // noop
    }
}
