<?php

namespace micmania1\SilverStripeCli\Docker;

use Docker\Context\ContextBuilder;
use Docker\API\Model\ContainerConfig;
use Docker\API\Model\PortBinding;
use Docker\API\Model\HostConfig;
use Docker\Context\Context;
use RandomLib\Factory;
use SecurityLib\Strength;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;

use micmania1\SilverStripeCli\Commands\BaseCommand;

class MariaDbService extends AbstractService
{
	public function getImageName()
	{
		return 'mariadb-shared';
	}

	/**
     * {@inheritdoc}
	 */
	protected function getImageBuilder()
	{
		$builder = new ContextBuilder();
		$builder->from('mariadb:latest');

        return $builder;
	}

	protected function getContainerConfig()
	{
		$containerConfig = new ContainerConfig();
		$containerConfig->setImage($this->getImageName());
		$containerConfig->setTty(true);
        $containerConfig->setEnv([
            'MYSQL_ROOT_PASSWORD=rootpass',
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
}
