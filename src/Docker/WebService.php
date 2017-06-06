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

class WebService extends AbstractService
{
	const MAJOR_VERSION = 1;

	public function getImageName()
	{
		return 'sscli-web:' . self::MAJOR_VERSION;
	}

	public function status(OutputInterface $output)
	{
		parent::status($output);

		$this->displayDetails($output);
	}

	protected function getImageBuilder()
	{
		$builder = new ContextBuilder();
		$builder->from('debian:stretch-slim');

		// Install, MySQL, Apache, PHP
		$builder->run('apt-get update -qq');
		$builder->run('apt-get install -qqy mariadb-server');
		$builder->run('apt-get install -qqy apache2 libapache2-mod-php php-cli php-common php-tidy php-gd php-intl php-apcu php-curl php-xdebug php-mcrypt php-mysql php-mbstring php-dom');

		// Install other useful stuff
		$builder->run('apt-get install -qqy vim lynx git-core');

		// Configure PHP
		$builder->run('sed -i \'s/;date.timezone =/date.timezone = Pacific\/Auckland/\' /etc/php/7.0/apache2/php.ini');
		$builder->run('sed -i \'s/;date.timezone =/date.timezone = Pacific\/Auckland/\' /etc/php/7.0/cli/php.ini');

		// Disable default site and enable mysite
		$builder->run('a2enmod rewrite');
		$builder->run('a2dissite 000-default.conf');
		$builder->copy('mysite.apache.conf', '/etc/apache2/sites-available/mysite.conf');
		$builder->run('a2ensite mysite.conf');

		// Copy and prepare startup scripts
		$builder->copy('docker-startup', '/opt/docker-startup');
		$builder->run('chmod +x /opt/docker-startup');

		return $builder;
	}

	protected function getContainerConfig()
	{
		$containerConfig = new ContainerConfig();
		$containerConfig->setImage($this->getImageName());
		$containerConfig->setVolumes(['/var/www/mysite' => (object) []]);
		$containerConfig->setTty(true);
		$containerConfig->setCmd('/opt/docker-startup');
		$containerConfig->setExposedPorts(['80/tcp' => (object)[]]);

		$uid = posix_getuid();
		$userInfo = posix_getpwuid($uid);
		$userName = $userInfo['name'];  

		$gid = posix_getgid();
		$groupInfo = posix_getgrgid($gid);
		$groupName = $groupInfo = $groupInfo['name'];

		$hostPort = $this->generateWebPort();

		$randomId = $this->getRandomId();
		$containerConfig->setEnv([
			sprintf('SSCLI_ID=%s', $randomId),
			sprintf('SSCLI_USERNAME=%s', $userName),
			sprintf('SSCLI_UID=%d', $uid),
			sprintf('SSCLI_GROUPNAME=%s', $groupName),
			sprintf('SSCLI_HOST_PORT=%d', $hostPort),
			sprintf('SSCLI_GID=%d', $gid),
			sprintf('SS_DATABASE_USERNAME=%s_%s', $this->generateDatabaseUser(), $randomId),
			sprintf('SS_DATABASE_PASSWORD=%s', $this->generateDatabasePassword()),
			sprintf('SS_DATABASE_SERVER=%s', $this->getDatabaseHost()),
			sprintf('SS_DATABASE_NAME=%s_%s', $this->generateDatabaseName(), $randomId),
			sprintf('SS_DATABASE_PORT=%d', '3306'),
		]);

		// Map ports
		$portBinding = new PortBinding();
		$portBinding->setHostPort($hostPort);
		$portBinding->setHostIp('0.0.0.0');
		$map = new \ArrayObject();
		$map['80/tcp'] = [$portBinding];

		$hostConfig = new HostConfig();
		$hostConfig->setBinds([$this->getProject()->getRootDirectory() . ':/var/www/mysite']);
		$hostConfig->setPortBindings($map);
		$containerConfig->setHostConfig($hostConfig);

		return $containerConfig;
	}

	protected function copyFixtures(Context $context)
	{
		$buildDir = $context->getDirectory() . DIRECTORY_SEPARATOR;
		$this->copyFixture('mysite.apache.conf', $buildDir);
		$this->copyFixture('docker-startup', $buildDir);
	}

	protected function generateDatabaseUser()
	{
		return $this->getProject()->getName();
	}

	protected function generateDatabasePassword()
	{
		$chars = 'abcdefghijklmnopqrstuvqxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_1234567890';
		return $this->getRandomGenerator()->generateString(32, $chars);
	}

	protected function getDatabaseHost()
	{
		return 'localhost';
	}

	protected function generateDatabaseName()
	{
		return $this->getProject()->getName();
	}

	/**
	 * This random id will be used by the container internally to ensure we
	 * don't get database name clashes and such.
	 *
	 * @return string
	 */
	protected function getRandomId()
	{
		return $this->getRandomGenerator()->generateString(6, 'abcdef1234567890');
	}

	protected function getRandomGenerator()
	{
		$factory = new Factory;
		return $factory->getGenerator(new Strength(Strength::MEDIUM));
	}

	protected function generateWebPort()
	{
		return (string) $this->getRandomGenerator()->generateInt(8000, 8999);
	}

	protected function displayDetails(OutputInterface $output) 
	{
		$this->displayCmsDetails($output);
		$this->displayDatabaseDetails($output);
	}

	protected function displayCmsDetails(OutputInterface $output)
	{
		$env = $this->getEnvVars();
		$dotEnv = $this->getProject()->getDotEnv();

		if(isset($dotEnv['SS_DEFAULT_ADMIN_USERNAME'], $dotEnv['SS_DEFAULT_ADMIN_PASSWORD'])) {
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
		$env = $this->getEnvVars();
		$table = new Table($output);
		$table->setHeaders([new TableCell('Database Access', ['colspan' => 2])]);
		$table->setStyle('compact');
		$table->setRows([
			['Database name', $env['SS_DATABASE_NAME']],
			['Username', $env['SS_DATABASE_USERNAME']],
			['Password', $env['SS_DATABASE_PASSWORD']],
			['Host', $env['SS_DATABASE_SERVER']],
			['Port', $env['SS_DATABASE_PORT']],
		]);
		$table->setColumnWidth(0, ceil(BaseCommand::COLUMN_LENGTH / 2));
		$table->setColumnWidth(1, ceil(BaseCommand::COLUMN_LENGTH / 2));
		$table->render();
		$output->writeln('');
	}
}
