<?php

namespace micmania1\SilverStripeCli;

use Symfony\Component\Console\Application as BaseApplication;
use RandomLib\Factory as RandomGenerator;

class Application extends BaseApplication
{
    /**
     * @var RandomGenerator
     */
    protected $randomGenerator;

    public function setRandomGenerator(RandomGenerator $generator)
    {
        $this->randomGenerator = $generator;
    }

    public function getRandomGenerator()
    {
        return $this->generator;
    }
}
