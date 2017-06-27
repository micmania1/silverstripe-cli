<?php

namespace micmania1\SilverStripeCli\Console;

use Symfony\Component\Console\Output\ConsoleOutput;

class Output extends ConsoleOutput
{
    /**
     * Clears the previously written line
     */
    public function clearLine()
    {
        // Move the cursor to the beginning of the line
        $this->write("\x0D");

        // Erase the line
        $this->write("\x1B[2K");
    }

    public function emptyLine()
    {
        $this->writeln('');
    }

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
    public function writeStatus($message, $status = null, $type = null)
    {
        $message = ' ' . trim($message);

        // Because we can have tags in our message which won't be shown, we need
        // to account for these in the padding by stripping them to calculate
        // how much we actually need to pag.
        $tagLength = strlen($message) - strlen(strip_tags($message));

        $message = str_pad($message, $this->getColumnLength() + $tagLength, '.');

        if (!empty($status)) {
            $length = strlen($status);
            $message = substr($message, 0, $length * -1);

            if ($type) {
                $message = sprintf(
                    '%s<%3$s>%s</%3$s>',
                    $message,
                    $status,
                    $type
                );
            } else {
                $message = sprintf('%s%s', $message, $status);
            }
        }

        $this->write($message);
    }

    /**
     * @return int
     */
    protected function getColumnLength()
    {
        $length = getenv('COLUMNS');
        if (empty($length) || $length > 80) {
            $length = 80;
        }

        if ($length < 30) {
            $length = 30;
        }

        return $length;
    }
}
