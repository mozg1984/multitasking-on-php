<?php
/**
 * Creates one main process and sets specified task with binded parameters for 5 child processes
 */

$process = new Process();

$task = function ($parameters) {
    // Here you can use your binded parameters
    if ($parameters['is_test']) {
    	echo 'Time = ' . $parameters['time'];
    }
};

// You can bind your parameters to task and use later in task
$task = Process::bindParameters($task, [
	'time' => time(),
	'is_test' => true
]);

// Second parameter sets number of tasks (by default = 1)
$process->addTask($task, 5);

$process->start();