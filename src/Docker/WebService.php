<?php

namespace micmania1\SilverStripeCli\Docker;

use Docker\Context\ContextBuilder;
use Docker\API\Model\ContainerConfig;
use Docker\API\Model\PortBinding;
use Docker\API\Model\HostConfig;
use Docker\Context\Context;

class WebService extends AbstractService
{
	const MAJOR_VERSION = 1;

	public function getImageName()
	{
		return 'sscli-web:' . self::MAJOR_VERSION;
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

		$cliFile = $this->getProject()->getCliFile();
		$dbUser = $cliFile->getOption('db_user');
		$dbPass = $cliFile->getOption('db_pass');

		$containerConfig->setEnv([
			sprintf('SSCLI_USERNAME=%s', $userName),
			sprintf('SSCLI_UID=%d', $uid),
			sprintf('SSCLI_GROUPNAME=%s',$groupName),
			sprintf('SSCLI_GID=%d', $gid),
			sprintf('SS_DATABASE_USERNAME=%s', $dbUser),
			sprintf('SS_DATABASE_PASSWORD=%s', $dbPass),
			sprintf('SS_DATABASE_SERVER=%s', "localhost"),
			sprintf('SS_DATABASE_NAME=%s', $dbUser),
		]);

		// Map ports
		$portBinding = new PortBinding();
		$portBinding->setHostPort('8080');
		$portBinding->setHostIp('0.0.0.0');
		$map = new \ArrayObject();
		$map['80/tcp'] = [$portBinding];

		$hostConfig = new HostConfig();
		$hostConfig->setBinds([getcwd() . ':/var/www/mysite']);
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
}
