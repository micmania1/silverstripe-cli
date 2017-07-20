<?php

namespace micmania1\SilverStripeCli\Console;

use Symfony\Component\Console\Output\OutputInterface as BaseOutputInterface;

interface OutputInterface extends BaseOutputInterface
{
    /**
     * Clears the previously written line
     */
    public function clearLine();

    /**
     * Adds an empty line to the console
     */
    public function emptyLine();

    /**
     * Writes a status line
     *
     * @example
     *  Some mesage......................OK
     *  Some other message.............FAIL
     *
     *  @param string $message
     *  @param string $status eg. <type>OK</type>
     *  @param string $type eg. <info>status</info>
     */
    public function writeStatus($message, $status = null, $type = null);

    /**
     * Displays the environment details (web and db)
     *
     * @param array $vars
     */
    public function displayEnvironmentDetails(array $vars);
}
