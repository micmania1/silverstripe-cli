<?php

namespace micmania1\SilverStripeCli;

use Symfony\Component\Console\Output\OutputInterface;

interface ServiceInterface
{
	/**
	 * Returns a unique identifier for the service
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * Build the service
	 */
	public function build(OutputInterface $output);

	/**
	 * Whether the service exists and is ready to start
	 *
	 * @return boolean
	 */
	public function exists();

	/**
	 * Start the service
	 */
	public function start(OutputInterface $output);

	/**
	 * Stop the service
	 */
	public function stop();

	/**
	 * Destroy the service
	 */
	public function destroy();

	/**
	 * Import the service
	 */
	public function import();

	/**
	 * Export the service
	 */
	public function export();	
}
