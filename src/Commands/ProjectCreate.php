<?php

namespace micmania1\SilverStripeCli\Commands;

use micmania1\SilverStripeCli\Helpers\ProcessSpinner;
use micmania1\SilverStripeCli\Helpers\Spinner;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

use Symfony\Component\Process\Process;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

use Cz\Git;

class ProjectCreate extends BaseCommand
{
	const WEB_DIRECTORY = 'www';

	/**
	 * @var string
	 */
	protected $directory;

	/**
	 * @var boolean
	 */
	protected $force = false;

	/**
	 * @var boolean
	 */
	protected $gitInit = true;

	/**
	 * The SilverStripe version to install. We only support 4+.
	 */
	protected $version = '@dev';

	protected function configure()
	{
		// Set name and descriptions
		$this->setName('new')
			->setDescription('Create a new SilverStripe project')
			->setHelp('This will create a skeleton SilverStripe app and initialise its git repo');

		// Add command arguments
		$this->addArgument('directory', InputArgument::REQUIRED, 'The directory of the project');
		$this->addArgument('version', InputArgument::OPTIONAL, 'SilverStripe version (compatible with composer)');

		// Add command options
		$this->addOption('exclude-git', null, InputOption::VALUE_NONE, 'Don\'t initialise a git repo');
		$this->addOption('force', 'f', InputOption::VALUE_NONE, 'Remove existing directory');
	}

	/**
	 * Perform any validation here and assign variables
	 *
	 * {@inheritdoc}
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		$this->directory = $input->getArgument('directory');
		$this->force = $input->getOption('force');
		$this->gitInit = !$input->getOption('exclude-git');

		$version = $input->getOption('version');
		if($version) {
			$this->setSilverStripeVersion($version);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if($this->isForced()) {
			if(!$this->removeExistingCliProject($input, $output)) {
				return;
			}
		}

		$this->runComposer($output);

		$this->copyFixtureFileToProject($output, $this->getCliFile());
		$this->copyFixtureFileToProject($output, '.env');
		$this->copyFixtureFileToProject($output, 'Dockerfile');
		$this->copyFixtureFileToProject($output, 'mysite.apache.conf', 'conf/mysite.apache.conf');
		$this->copyFixtureFileToProject($output, 'docker-startup', 'conf/docker-startup');

		if($this->gitInit) {
			$this->initGitRepo($output);
		}

		$output->writeln('');
		$output->writeln(" <info>Project created</info> \xF0\x9F\x8D\xBA");
		$output->writeln('');
	}

	/**
	 * Returns the relative project directory
	 *
	 * @return string
	 */
	protected function getDirectory()
	{
		$filesystem = new Filesystem();

		$directory = $this->directory;
		if(!$filesystem->isAbsolutePath($directory)) {
			$directory = getcwd() . DIRECTORY_SEPARATOR . $directory;
		}

		return $filesystem->makePathRelative($directory, getcwd());
	}

	protected function getSilverStripeVersion()
	{
		return $this->version;
	}

	protected function setSilverStripeVersion($version)
	{
		$this->version = $version;
	}

	protected function getWebDirectory()
	{
		return $this->getDirectory() . self::WEB_DIRECTORY;
	}

	/**
	 * @return boolean
	 */
	protected function isForced()
	{
		return (boolean) $this->force;
	}

	/**
	 * @param string $file
	 *
	 * @return string
	 */
	protected function getFixtureFile($file)
	{
		return FIXTURES_DIR . DIRECTORY_SEPARATOR . $file;
	}

	/**
	 * @param string $file
	 *
	 * @return string
	 */
	protected function getProjectFile($file)
	{
		return $this->getDirectory() . DIRECTORY_SEPARATOR . $file;
	}

	/**
	 * Removes an existing cli project
	 *
	 * @param OutputInterface
	 */
	protected function removeExistingCliProject(InputInterface $input, OutputInterface $output)
	{
		$dir = $this->getDirectory();
		if(!$this->isCliProject($dir)) {
			$helper = $this->getHelper('question');

			$output->writeln('The directory isn\'t a SilverStripe Cli directory but already exists.');
			$question = new ConfirmationQuestion(
				'Remove the directory [<comment>y/N</comment>]: ', 
				false
			);

			if(!$helper->ask($input, $output, $question)) {
				$output->writeln('<error>Aborted</error>');
				return false;
			} else {
				$output->writeln('');
			}
		}

		$filesystem = new Filesystem();

		try {
			$filesystem->remove($dir);
			$status = 'OK';
			$type = 'info';
		} catch (IOExceptionInterface $e) {
			$status = 'FAIL';
			$type = 'error';
		}

		$message = sprintf('Removing existing project at %s', $dir);
		$spinner = new Spinner($output, $message);
		$spinner->run($status, $type);

		return true;
	}

	/**
	 * Run composer create project
	 *
	 * @param OutputInterface
	 */
	protected function runComposer($output)
	{
		$command = sprintf(
			'%s create-project silverstripe/installer %s %s --prefer-dist',
			COMPOSER_BIN,
			$this->getWebDirectory(),
			$this->getSilverStripeVersion()
		);

		$process = new Process($command);
		$spinner = new ProcessSpinner(
			$output,
			sprintf('Downloading SilverStripe <info>%s</info>', $this->getSilverStripeVersion()),
			$process
		);
		$spinner->run();
	}

	/**
	 * Copies a fixture file over to the current project
	 *
	 * @param OutputInterface $output
	 * @param string $file
	 */
	protected function copyFixtureFileToProject(OutputInterface $output, $file, $target = null)
	{
		if(!$target) {
			$target = $file;
		}

		$target = $this->getProjectFile($target);
		if(!is_dir($dir = dirname($target))) {
			mkdir($dir, 0775, true);
		}

		$source = fopen($this->getFixtureFile($file), 'r');
		$dest = fopen($target, 'w');

		$result = stream_copy_to_stream($source, $dest);

		if($result !== FALSE) {
			$status = 'OK';
			$type = 'info';
		} else {
			$status = 'FAIL';
			$type = 'error';
		}

		$spinner = new Spinner($output, sprintf('Creating %s', $file));
		$spinner->run($status, $type);

		if($result === FALSE) {
			throw new Exception(sprintf(
				'Unable to copy from %s to %s',
				$this->getFixtureFile($file),
				$this->getProjectFile($file)
			));
		}
	}

	/**
	 * Initialize the git repo
	 *
	 * @param OutputInterface $output
	 */
	protected function initGitRepo(OutputInterface $output)
	{
		$command = sprintf('cd %s && git init', $this->getDirectory());
		$process = new Process($command);

		$spinner = new ProcessSpinner($output, 'Initialising Git', $process);
		$spinner->run();
	}
}
