<?php

namespace micmania1\SilverStripeCli\Helpers;

use Dotenv\Loader;

class DotEnvMemoryLoader extends Loader
{
	/**
	 * @var array
	 */
	protected $vars = [];

	/**
	 * Instead of writing our environment variables to _SERVER and putenv,
	 * we're only going to store them in an array
	 *
	 * {@inheritdoc}
	 */
	public function setEnvironmentVariable($name, $value = null)
	{
        list($name, $value) = $this->normaliseEnvironmentVariable($name, $value);

		$this->vars[$name] = $value;
	}

	/**
	 * @return array
	 */
	public function getEnvVars()
	{
		if(!empty($this->vars)) {
			return $this->vars;
		}

		$this->load();

		return $this->vars;
	}
}
