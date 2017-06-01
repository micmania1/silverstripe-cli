<?php

namespace micmania1\SilverStripeCli;

use Closure;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An environment is our interface to backend infrastructure. An
 * environment may represent a single service (ie. vagrant LAMP stack)
 * or may compose of multiple services (ie. Docker; web, db etc)
 */
interface EnvironmentInterface
{
	/**
	 * This will build the initial environment
	 */
	public function build(OutputInterface $output);

	/**
	 * This will give the status of the current environment
	 *
	 * @return array
	 */
	public function status();

	/**
	 * This will launch the current environment
	 *
	 * @return array
	 */
	public function start(OutputInterface $output);

	/**
	 * This will stop the current environment
	 *
	 * @return array
	 */
	public function stop();

	/**
	 * This will export an sspak out of the current environment
	 *
	 * @return string file path to sspak file
	 */
	public function export();

	/**
	 * This will import an sspak into the current environment
	 *
	 * @param file path to sspak file
	 *
	 * @return boolean
	 */
	public function import($file);
}
