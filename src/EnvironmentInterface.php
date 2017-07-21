<?php

namespace micmania1\SilverStripeCli;

use micmania1\SilverStripeCli\Console\OutputInterface;

/**
 * When we talk about an Environment in SilverStripe CLI, we're talking about
 * whatever is behind running the website. This could be docker, vagrant or some
 * other tool. Even if multiple services make up the stack, we refer to this
 * simply as 'Environment'
 */
interface EnvironmentInterface
{
    /**
     * This will build the initial environment.
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    public function build(OutputInterface $output);

    /**
     * This will give the status of the current environment
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    public function status(OutputInterface $output);

    /**
     * This will launch the current environment
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    public function start(OutputInterface $output);

    /**
     * This will stop the current environment
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    public function stop(OutputInterface $output);

    /**
     * This will export an sspak out of the current environment
     *
     * @param OutputInterface $output
     * @param string $outputFile
     *
     * @return boolean
     */
    public function export(OutputInterface $output, $outputFile);

    /**
     * This will import an sspak into the current environment
     *
     * @param OutputInterface $output
     * @param string $outputFile
     *
     * @return boolean
     */
    public function import(OutputInterface $output, $outputFile);

    /**
     * Returns the URL of the environment
     *
     * @return string
     */
    public function getUrl();
}
