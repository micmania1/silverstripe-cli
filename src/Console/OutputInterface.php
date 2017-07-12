<?php

namespace micmania1\SilverStripeCli\Console;

interface OutputInterface
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
}
