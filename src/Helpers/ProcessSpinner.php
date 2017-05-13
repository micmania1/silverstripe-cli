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
		
		while($this->process->isRunning()) {
			$this->spin();
		}

		$this->clearLine();

		if(!$this->process->isSuccessful()) {
			$this->updateStatus('FAIL', 'error');
			$this->output->writeln('');

			throw new ProcessFailedException($this->process);
		}

		$this->updateStatus('OK', 'info');
		$this->output->writeln('');
	}

}
