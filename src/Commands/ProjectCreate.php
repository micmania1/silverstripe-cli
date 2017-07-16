<?php

namespace micmania1\SilverStripeCli\Commands;

use micmania1\SilverStripeCli\Helpers\ProcessSpinner;
use micmania1\SilverStripeCli\Helpers\Spinner;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Console\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use Cz\Git;

class ProjectCreate extends BaseCommand
{
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
    protected $version = '@beta';

    /**
     * @var Project
     */
    protected $project;

    /**
     * @var Filesystem
     */
    protected $filesyste;

    /**
     * The current working directory for this command to work from
     *
     * @var string $cwd
     */
    protected $cwd;

    /**
     * @param Filesystem $filesystem
     * @param string $cwd
     */
    public function __construct(Filesystem $filesystem, $cwd)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
        $this->cwd = $cwd;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        // Set name and descriptions
        $this->setName('new')
            ->setDescription('Create a new SilverStripe project')
            ->setHelp(
                'This will create a skeleton SilverStripe app and initialise '
                . 'its git repo'
            );

        // Add command arguments
        $this->addArgument(
            'directory',
            InputArgument::REQUIRED,
            'The directory of the project'
        );
        $this->addArgument(
            'version',
            InputArgument::OPTIONAL,
            'SilverStripe version (compatible with composer)'
        );

        // Add command options
        $this->addOption(
            'exclude-git',
            null,
            InputOption::VALUE_NONE,
            'Don\'t initialise a git repo'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Remove existing directory'
        );
    }

    /**
     * Perform any validation here and assign variables
     *
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->force = $input->getOption('force');
        $this->gitInit = !$input->getOption('exclude-git');

        $version = $input->getOption('version');
        if ($version) {
            $this->setSilverStripeVersion($version);
        }

        $directory = $input->getArgument('directory');
        if (!$this->filesystem->isAbsolutePath($directory)) {
            $directory = $this->cwd . DIRECTORY_SEPARATOR . $directory;
        }
        $directory = $this->filesystem->makePathRelative($directory, $this->cwd);

        $this->project = new Project($directory);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->isForced() && !$this->removeExistingProject($input, $output)) {
            return;
        }

        $this->runComposer($output);

        $cliFile = $this->getProject()
            ->getCliFile()
            ->getName();
        $this->copyFixtureFileToProject($output, $cliFile);

        $this->copyFixtureFileToProject($output, '.env');
        $this->removeUnnecessaryProjectFiles($output);

        if ($this->gitInit) {
            $this->initGitRepo($output);
        }

        $output->emptyLine();
        $output->writeln(" <info>Project created</info> \xF0\x9F\x8D\xBA");
        $output->emptyLine();
    }

    /**
     * @return Project
     */
    protected function getProject()
    {
        return $this->project;
    }

    /**
     * @return string
     */
    protected function getSilverStripeVersion()
    {
        return $this->version;
    }

    /**
     * @param string
     */
    protected function setSilverStripeVersion($version)
    {
        $this->version = $version;
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
        return rtrim(FIXTURES_DIR, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $file;
    }

    /**
     * Removes an existing cli project
     *
     * @param OutputInterface
     */
    protected function removeExistingProject(
        InputInterface $input,
        OutputInterface $output
    ) {
        $project = $this->getProject();
        if (!$project->isCli()) {
            $helper = $this->getHelper('question');

            $output->writeln(
                'The directory isn\'t a SilverStripe Cli project.'
            );
            $question = new ConfirmationQuestion(
                'Do you want to remove it [<comment>y/N</comment>]: ',
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<error>Aborted</error>');
                return false;
            } else {
                $output->writeln('');
            }
        }

        $message = sprintf(
            'Removing existing project at %s',
            $project->getRootDirectory()
        );
        $spinner = new Spinner($output, $message);

        try {
            $this->filesystem->remove($project->getRootDirectory());
            $status = 'OK';
            $type = 'success';
            $spinner->run($status, $type);
        } catch (IOExceptionInterface $e) {
            $status = 'FAIL';
            $type = 'error';
            $spinner->run($status, $type);

            throw $e;
        }


        return true;
    }

    /**
     * Run composer create project
     *
     * @param OutputInterface
     */
    protected function runComposer(OutputInterface $output)
    {
        $command = sprintf(
            '%s create-project silverstripe/installer %s %s --prefer-dist',
            COMPOSER_BIN,
            $this->getProject()->getWebDirectory(),
            $this->getSilverStripeVersion()
        );

        $process = new Process($command);
        $spinner = new ProcessSpinner(
            $output,
            sprintf(
                'Downloading SilverStripe <info>%s</info>',
                $this->getSilverStripeVersion()
            ),
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
    protected function copyFixtureFileToProject(
        OutputInterface $output,
        $file,
        $target = null
    ) {
        if (!$target) {
            $target = $file;
        }

        $target = $this->getProject()->getFile($target);
        if (!is_dir($dir = dirname($target))) {
            mkdir($dir, 0775, true);
        }

        $source = fopen($this->getFixtureFile($file), 'r');
        $dest = fopen($target, 'w');

        $result = stream_copy_to_stream($source, $dest);

        $message = sprintf('Creating %s', $file);
        $spinner = new Spinner($output, $message);
        if ($result !== false) {
            $spinner->run('OK', 'success');
        } else {
            $spinner->run('FAIL', 'error');
        }

        if ($result === false) {
            throw new Exception(sprintf(
                'Unable to copy from %s to %s',
                $this->getFixtureFile($file),
                $this->getProject()->getFile($file)
            ));
        }
    }

    protected function removeUnnecessaryProjectFiles(OutputInterface $output)
    {
        $message = 'Removing unnecessary project files';
        $spinner = new Spinner($output, $message);
        try {
            $project = $this->getProject();
            $project->removeFile('www/install.php');
            $project->removeFile('www/install-frameworkmissing.html');

            $spinner->run('OK', 'success');
        } catch (IOExceptionInterface $e) {
            $spinner->run('FAIL', 'error');

            throw $e;
        }

        return true;
    }

    /**
     * Initialize the git repo
     *
     * @param OutputInterface $output
     */
    protected function initGitRepo(OutputInterface $output)
    {
        $project = $this->getProject();

        $command = sprintf('cd %s && git init', $project->getRootDirectory());
        $process = new Process($command);

        $spinner = new ProcessSpinner($output, 'Initialising Git', $process);
        $spinner->run();
    }
}
