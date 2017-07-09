<?php

namespace micmania1\SilverStripeCli\Docker;

use Docker\Context\ContextBuilder;
use Docker\API\Model\ContainerConfig;
use Docker\API\Model\PortBinding;
use Docker\API\Model\HostConfig;
use Docker\Context\Context;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Exception\RuntimeException;

use micmania1\SilverStripeCli\Commands\BaseCommand;

class MariaDbService extends AbstractService
{
    public function getImageName()
    {
        return 'sscli-db';
    }

    /**
     * {@inheritdoc}
     */
    protected function getImageBuilder($config = [])
    {
        $builder = new ContextBuilder();
        $builder->from('mariadb:latest');

        return $builder;
    }

    protected function getContainerConfig($config = [])
    {
        if (!isset($config['rootPass'])) {
            throw new RuntimeException('rootPass config missing');
        }

        $containerConfig = new ContainerConfig();
        $containerConfig->setImage($this->getImageName());
        $containerConfig->setTty(true);
        $containerConfig->setEnv([
            sprintf('MYSQL_ROOT_PASSWORD=%s', $config['rootPass']),
        ]);
        $containerConfig->setExposedPorts(['3306/tcp' => (object)[]]);

        // Map ports
        $portBinding = new PortBinding();
        $portBinding->setHostPort('9000');
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
