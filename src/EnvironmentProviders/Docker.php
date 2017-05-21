<?php

namespace micmania1\SilverStripeCli\EnvironmentProviders;

use Closure;

use Docker\Docker as DockerService;
use Docker\Context\ContextBuilder;
use Docker\Manager\ImageManager;
use Docker\API\Model\Buildinfo;
use Docker\API\Model\ContainerConfig;
use Docker\API\Model\HostConfig;
use Docker\API\Model\PortBinding;

use Symfony\Component\Filesystem\Filesystem;

use micmania1\SilverStripeCli\Model\Project;

class Docker implements Provider
{
	/**
	 * @var Project
	 */
	protected $project;

	/**
	 * @var DockerService
	 */
	protected $docker;

	/**
	 * @param Project $project
	 */
	public function __construct(Project $project)
	{
		$this->project = $project;
		$this->docker = new DockerService();
	}

	public function build(Closure $callback)
	{
		$builder = new ContextBuilder();
		$builder->from('debian');

		// Configure MySQL
		$builder->run('echo "mariadb-server mysql-server/root_password password rootpass" | debconf-set-selections');
		$builder->run('echo "mariadb-server mysql-server/root_password_again password rootpass" | debconf-set-selections');

		// Install, MySQL, Apache, PHP
		$builder->run('apt-get update -qq');
		$builder->run('apt-get install -qqy apache2 mariadb-server');
		$builder->run('apt-get install -qqy libapache2-mod-php5 php5-cli php5-common php5-tidy php5-gd php5-intl php5-apcu php5-curl php5-xdebug php5-xhprof php5-mcrypt php5-mysql');

		// Install other useful stuff
		$builder->run('apt-get install -qqy vim lynx git-core');

		// Configure PHP
		$builder->run('sed -i \'s/;date.timezone =/date.timezone = Pacific\/Auckland/\' /etc/php5/apache2/php.ini');
		$builder->run('sed -i \'s/;date.timezone =/date.timezone = Pacific\/Auckland/\' /etc/php5/cli/php.ini');

		// Disable default site and enable mysite
		$builder->run('a2enmod rewrite');
		$builder->run('a2dissite 000-default.conf');
		$builder->copy('mysite.conf', '/etc/apache2/sites-available/mysite.conf');
		$builder->run('a2ensite mysite.conf');

		// Copy and prepare startup scripts
		$builder->copy('docker-startup', '/opt/docker-startup');
		$builder->run('chmod +x /opt/docker-startup');

		$project = $this->getProject();
		$imageManager = $this->docker->getImageManager();
		$context = $builder->getContext();

		$buildDir = $context->getDirectory() . DIRECTORY_SEPARATOR;
		copy($this->getFixtureFile('mysite.apache.conf'), $buildDir . 'mysite.conf');
		copy($this->getFixtureFile('docker-startup'), $buildDir . 'docker-startup');

		$stream = $imageManager->build(
			$context->toStream(), [
				't' => 'silverstripe-cli:' . $project->getCliVersion(),
			], 
			ImageManager::FETCH_STREAM
		);

		$stream->onFrame($callback);
		$stream->wait();
	}

	public function status()
	{
		return [];
	}

	public function up()
	{
		$containerManager = $this->docker->getContainerManager();
		
		$containerConfig = new ContainerConfig();
		$containerConfig->setImage('silverstripe-cli:0-experimental');
		$containerConfig->setVolumes(['/var/www/mysite' => (object) []]);
		$containerConfig->setTty(true);
		$containerConfig->setCmd('/opt/docker-startup');

		$uid = posix_getuid();
		$userInfo = posix_getpwuid($uid);
		$userName = $userInfo['name'];  

		$gid = posix_getgid();
		$groupInfo = posix_getgrgid($gid);
		$groupName = $groupInfo = $groupInfo['name'];

		$containerConfig->setEnv([
			sprintf('SSCLI_USERNAME=%s', $userName),
			sprintf('SSCLI_UID=%d', $uid),
			sprintf('SSCLI_GROUPNAME=%s',$groupName),
			sprintf('SSCLI_GID=%d', $gid)
		]);

		$containerConfig->setExposedPorts(['80/tcp' => (object)[]]);

		$hostConfig = new HostConfig();

		// Map volume
		$hostConfig->setBinds([getcwd() . ':/var/www/mysite']);

		// Map ports
		$map = new \ArrayObject();
		$portBinding = new PortBinding();
		$portBinding->setHostPort('8080');
		$portBinding->setHostIp('0.0.0.0');
		$map['80/tcp'] = [$portBinding];
		$hostConfig->setPortBindings($map);

		$containerConfig->setHostConfig($hostConfig);

		$result = $containerManager->create($containerConfig, ['name' => 'my-container']);
		// $attachStream = $containerManager->attach($result->getId(), [
		// 	'logs' => true,
		// 	'stream' => true,
		// 	'stdin' => true,
		// 	'stdout' => true,
		// 	'stderr' => true
		// ]);
		// 
		// $attachStream->onStdout(function($stdout) {
		// 	echo $stdout;
		// });

		// $attachStream->onStderr(function($stderr) {
		// 	echo $stderr;
		// });

		// $attachStream->onStdin(function($stdin) {
		// 	echo $stdin;
		// });

		$containerManager->start($result->getId());
		// $containerManager->wait($result->getId());
	}

	public function down()
	{
		return [];
	}

	public function export()
	{
		throw new \Exception('Not implemented');
	}

	public function import($file)
	{
		throw new \Exception('Not implemented');
	}

	protected function getProject()
	{
		return $this->project;
	}

	/**
	 * @param string $file
	 *
	 * @return string
	 */
	protected function getFixtureFile($file)
	{
		$file = FIXTURES_DIR . DIRECTORY_SEPARATOR . $file;

		$filesystem = new Filesystem();
		$rel = $filesystem->makePathRelative($file, getcwd());

		return rtrim($rel, DIRECTORY_SEPARATOR);
	}
}
