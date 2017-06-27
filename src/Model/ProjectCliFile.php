<?php

namespace micmania1\SilverStripeCli\Model;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Exception\RuntimeException;

class ProjectCliFile
{
    /**
     * Path to the config file
     *
     * @var string
     */
    protected $path;

    /**
     * Parsed content of the config file. Used as cache so we don't have to parse each
     * time we call getContent
     *
     * @var array
     */
    protected $content;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return basename($this->getPath());
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return array
     */
    public function getContent()
    {
        if (!empty($this->content)) {
            return $this->content;
        }

        return $this->content = Yaml::parse(file_get_contents($this->getPath()));
    }

    /**
     * Get config option.
     *
     * @param string $option
     *
     * @example
     * 	my.structured.object would look for $data[my][structured][object]
     *
     * @return mixed
     */
    public function getOption($option)
    {
        $parts = explode('.', $option);

        $current = $this->getContent();
        foreach ($parts as $part) {
            // If we have content and its not an array, then the format/option is
            // invalid
            if (!is_array($current)) {
                throw new RuntimeException(
                    sprintf('Invalid format %s', $option)
                );
            }

            // If the key doesn't exist then return false
            if (!array_key_exists($part, $current)) {
                throw new RuntimeException(
                    sprintf('Missing config option "%s"', $option)
                );
            }

            $current = $current[$part];
        }

        return $current;
    }
}
