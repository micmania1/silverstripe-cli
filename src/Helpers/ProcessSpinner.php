<?php

namespace micmania1\SilverStripeCli\Helpers;

use Symfony\Component\Process\Exception\ProcessFailedException;

class ProcessSpinner extends Spinner
{
    protected $process;

    public function __construct($output, $message, $process)
    {
        parent::__construct($output, $message);

        $this->process = $process;
    }

    public function run()
    {
        $this->process->start();
        
        while ($this->process->isRunning()) {
            $this->tick();
        }

        $this->output->clearLine();

        if (!$this->process->isSuccessful()) {
            $this->output->writeStatus($this->message, 'FAIL', 'error');
            $this->output->emptyLine();

            throw new ProcessFailedException($this->process);
        }

        $this->output->writeStatus($this->message, 'OK', 'success');
        $this->output->emptyLine();
    }
}
