<?php

namespace micmania1\SilverStripeCli\Console;

use Symfony\Component\Console\Output\ConsoleOutput;

class Output extends ConsoleOutput implements OutputInterface
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

    /**
     * Displays details for the web service including url and default cms admin
     *
     */
    public function displayEnvironmentDetails(array $vars)
    {
        if (isset($vars['SS_DEFAULT_ADMIN_USERNAME'])
            && isset($vars['SS_DEFAULT_ADMIN_PASSWORD'])
        ) {
            $adminUsername = $vars['SS_DEFAULT_ADMIN_USERNAME'];
            $adminPassword = $vars['SS_DEFAULT_ADMIN_PASSWORD'];
        } else {
            $adminUsername = '<warning>No default admin</warning>';
            $adminPassword = '<warning>No default admin</warning>';
        }

        $table = new Table($output);
        $table->setHeaders([new TableCell('Website Access', ['colspan' => 2])]);
        $table->setStyle('compact');
        $table->setRows([
            ['URL', sprintf('http://localhost:%d', $env['WEB_PORT'])],
            ['Admin URL', sprintf('http://localhost:%d/admin', $env['WEB_PORT'])],
            ['CMS Admin', $adminUsername],
            ['CMS Password', $adminPassword],
        ]);
        $table->setColumnWidth(0, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->setColumnWidth(1, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->render();

        $output->emptyLine();

        $table = new Table($output);
        $table->setHeaders([new TableCell('Database Access', ['colspan' => 2])]);
        $table->setStyle('compact');
        $table->setRows([
            ['Database name', $vars['SS_DATABASE_NAME']],
            ['Username', $vars['SS_DATABASE_USERNAME']],
            ['Password', $vars['SS_DATABASE_PASSWORD']],
            ['Host', $vars['DB_HOSTNAME']],
            ['Port', $vars['DB_PORT']],
        ]);
        $table->setColumnWidth(0, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->setColumnWidth(1, ceil(BaseCommand::COLUMN_LENGTH / 2));
        $table->render();

        $output->emptyLine();
    }
}
