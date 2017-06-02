<?php

namespace micmania1\SilverStripeCli\Helpers;

use micmania1\SilverStripeCli\Commands\BaseCommand;

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
	 * @return boolean
	 */
	public function run($status, $statusType)
	{
		$time = (time() + $this->time) * 1000000;
		$currentTime = time() * 1000000;

		while($currentTime < $time) {
			$currentTime += self::SLEEP;
			$this->tick();
		}

		$this->clearLine();
		$this->updateStatus($status, $statusType);
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

		$this->clearLine();

		// Figure out our spinner state
		switch($this->state) {
			case 1:
				$this->updateStatus('|');
				break;
			case 2:
				$this->updateStatus('/');
				break;
			case 3:
				$this->updateStatus('-');
				break;
			case 4:
			default:
				$this->updateStatus('\\');
				$this->state = 0;
				break;
		}

		usleep(self::SLEEP);
	}

	/**
	 * @param string $status
	 * @param string $type
	 */
	public function updateStatus($status = null, $type = null)
	{
		$message = ' ' . trim($this->message);

		// Because we can have tags in our message which won't be shown, we need
		// to account for these in the padding by stripping them to calculate
		// how much we actually need to pag.
		$tagLength = strlen($message) - strlen(strip_tags($message));

		$message = str_pad($message, BaseCommand::COLUMN_LENGTH + $tagLength, '.');

		if(!empty($status)) {
			$length = strlen($status);
			$message = substr($message, 0, $length * -1);

			if($type) {
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

		$this->output->write($message);
	}

	/**
	 * Clears the current line on the terminal
	 */
	public function clearLine() 
	{
		// Move the cursor to the beginning of the line
		$this->output->write("\x0D");

		// Erase the line
		$this->output->write("\x1B[2K");
	}
}
