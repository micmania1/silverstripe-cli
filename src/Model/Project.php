<?php

namespace micmania1\SilverStripeCli\Model;

use Symfony\Component\Filesystem\Filesystem;

use micmania1\SilverStripeCli\Helpers\DotEnvMemoryLoader;

class Project
{
    /**
     * Cli config file
     *
     * @const string
     */
    const CLI_FILE = '.silverstripe-cli.yaml';

    /**
     * Web directory
     *
     * @const string
     */
    const WEB_DIRECTORY = 'www';

    /**
     * @var ProjectConfigFile
     */
    protected $cliFile;

    /**
     * @param string $dir Project root directory
     */
    public function __construct($dir)
    {
        $this->dir = $dir;
    }

    public function getName()
    {
        $parts = explode(DIRECTORY_SEPARATOR, $this->getRootDirectory());

        return array_pop($parts);
    }

    /**
     * The root directory of the project
     *
     * @return string
     */
    public function getRootDirectory()
    {
        return rtrim($this->dir, DIRECTORY_SEPARATOR);
    }

    /**
     * Returns the project web directory
     *
     * @return string
     */
    public function getWebDirectory()
    {
        return $this->getFile(self::WEB_DIRECTORY);
    }

    /**
     * Construct a file path for a file within the current project. The file does not
     * have to exist already.
     *
     * @param string $file
     *
     * @return string
     */
    public function getFile($file)
    {
        return $this->getRootDirectory() . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * Removes a file or folder within the project
     *
     * @param string
     *
     * @return boolean
     *
     * @throws Symfony\Component\Filesystem\Exception\IOExceptionInterface
     */
    public function removeFile($file)
    {
        $file = $this->getFile($file);
        if (!file_exists($file)) {
            return true;
        }

        $filesystem = new Filesystem();
        $filesystem->remove($file);
    }

    /**
     * Returns the filepath for the cli config file.
     *
     * @return string
     */
    public function getCliFile()
    {
        if ($this->cliFile) {
            return $this->cliFile;
        }

        $path = $this->getFile(self::CLI_FILE);
        return $this->cliFile = new ProjectCliFile($path);
    }

    /**
     * Checks to see if the current directory is a cli project
     *
     * @return boolean
     */
    public function isCli()
    {
        return file_exists($this->getFile(self::CLI_FILE));
    }

    /**
     * Returns the project cli version so we can use a compatible environment
     *
     * @return string
     */
    public function getCliVersion()
    {
        return $this->getCliFile()->getOption('version');
    }

    /**
     * Loads the dot env file and returns its values as a key/value pair
     *
     * @return string[]
     */
    public function getDotEnv()
    {
        $file = $this->getFile('.env');
        $loader = new DotEnvMemoryLoader($file);

        return $loader->getEnvVars();
    }
}
