<?php

namespace App;

/**
 * Provides the possibility of calling task as Callable object
 */
abstract class Task 
{
    /**
     * @var array|null The default parameters
     */
    private $defaultParameters;

    /**
     *  @param array $defaultParameters The default parameters for executing current task
     */
    final public function __construct(array $defaultParameters = [])
    {
        $this->defaultParameters = $defaultParameters;
    }

    /**
     * Support for executing current task as a Callable object
     *
     * @param array $parameters Parameters for executing current task  
     */
    final public function __invoke(array $parameters = [])
    {
        $this->run(array_merge($this->defaultParameters, $parameters));
    }

    /**
     * Runs the current task
     *
     * This is a main method for task working 
     *
     * @param array $parameters Parameters for executing current task
     */
    abstract public function run(array $parameters);
}