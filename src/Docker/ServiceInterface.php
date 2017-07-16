<?php

namespace micmania1\SilverStripeCli\Docker;

use micmania1\SilverStripeCli\Console\OutputInterface;

interface ServiceInterface
{
    /**
     * @const string
     */
    const STATUS_NOT_READY = 'NOT READY';

    /**
     * @const string
     */
    const STATUS_RUNNING = 'RUNNING';

    /**
     * @const string
     */
    const STATUS_RESTARTING = 'RESTARTING';

    /**
     * @const string
     */
    const STATUS_PAUSED = 'PAUSED';

    /**
     * @const string
     */
    const STATUS_STOPPED = 'STOPPED';

    /**
     * Returns the human-friendly name of the container
     *
     * @return string
     */
    public function getName();

    /**
     * Build the service
     *
     * @param OutputInterface $output
     * @param array $config
     *
     * @return array
     */
    public function build(OutputInterface $output, array $config = []);

    /**
     * Checks that the image exists
     *
     * @return boolean
     */
    public function imageExists();

    /**
     * Checks that the container exists
     *
     * @return boolean
     */
    public function containerExists();

    /**
     * Checks whether the container is running
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    public function isRunning(OutputInterface $output);

    /**
     * Start the service
     *
     * @param OutputInterface $output
     * @param array $config
     *
     * @return boolean
     */
    public function start(OutputInterface $output, array $config = []);

    /**
     * Stop the service
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    public function stop(OutputInterface $output);

    /**
     * Destroy the docker container
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    public function destroyContainer(OutputInterface $output);

    /**
     * Remove the docker image
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    public function removeImage(OutputInterface $output);

    /**
     * Import the service
     */
    public function import();

    /**
     * Export the service
     */
    public function export();

    /**
     * Return the ip of a running instance
     *
     * @return string
     *
     * @throws RuntimeException if unable to obtain ip
     */
    public function getIp();

    /**
     * Returns an array of docker environmental variables
     *
     * @return string[]
     */
    public function getEnvVars();
}
