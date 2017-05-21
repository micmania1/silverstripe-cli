<?php

namespace micmania1\SilverStripeCli\EnvironmentProviders;

use Closure;

interface Provider
{
	/**
	 * This will build the initial environment
	 *
	 * @param Closure $callback - called each step of the build with line info
	 *
	 * @return boolean
	 */
	public function build(Closure $callback);

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
	public function up();

	/**
	 * This will stop the current environment
	 *
	 * @return array
	 */
	public function down();

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
