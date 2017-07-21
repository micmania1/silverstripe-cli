<?php

namespace micmania1\SilverStripeCli\Helpers;

class Spinner
{
    /**
     * Microseconds to sleep before each state
     *
     * @const int
     */
    const SLEEP = 80000;

    protected $output;

    protected $message;

    protected $state = 0;

    protected $time = 1;

    public function __construct($output, $message, $time = 1)
    {
        $this->output = $output;
        $this->message = $message;
        $this->time = $time;
    }

    /**
     * @param string $status
     * @param string $statusType
     *
     * @return boolean|null
     */
    public function run($status, $statusType)
    {
        $time = (time() + $this->time) * 1000000;
        $currentTime = time() * 1000000;

        while ($currentTime < $time) {
            $currentTime += self::SLEEP;
            $this->tick();
        }

        $this->output->clearLine();
        $this->output->writeStatus($this->message, $status, $statusType);
        $this->output->writeln('');
    }

    public function tick()
    {
        $this->spin();
    }

    /**
     * Perform state change - on to the next spin state
     */
    protected function spin()
    {
        $this->state++;

        $this->output->clearLine();

        // Figure out our spinner state
        switch ($this->state) {
            case 1:
                $this->output->writeStatus($this->message, '|');
                break;
            case 2:
                $this->output->writeStatus($this->message, '/');
                break;
            case 3:
                $this->output->writeStatus($this->message, '-');
                break;
            case 4:
            default:
                $this->output->writeStatus($this->message, '\\');
                $this->state = 0;
                break;
        }

        usleep(self::SLEEP);
    }
}
