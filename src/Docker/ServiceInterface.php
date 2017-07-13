<?php

namespace micmania1\SilverStripeCli\Docker;

use micmania1\SilverStripeCli\Console\OutputInterface;

interface ServiceInterface
{
    const STATUS_NOT_READY = 'NOT READY';

    const STATUS_RUNNING = 'RUNNING';

    const STATUS_RESTARTING = 'RESTARTING';

    const STATUS_PAUSED = 'PAUSED';

    const STATUS_STOPPED = 'STOPPED';

    /**
     * Returns a unique identifier for the service
     *
     * @return string
     */
    public function getName();

    /**
     * Build the service
     */
    public function build(OutputInterface $output, $config = []);

    /**
     * Whether the service exists and is ready to start
     *
     * @return boolean
     */
    public function exists();

    /**
     * Fetch the status of the service.
     *
     * @return string
     */
    public function status(OutputInterface $output);

    /**
     * Start the service
     */
    public function start(OutputInterface $output, array $config = []);

    /**
     * Stop the service
     */
    public function stop(OutputInterface $output);

    /**
     * Destroy the service
     */
    public function destroy();

    /**
     * Import the service
     */
    public function import();

    /**
     * Export the service
     */
    public function export();
}
