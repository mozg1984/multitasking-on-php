<?php
/**
 * Creates one main process and sets specified task for 5 child processes
 */

$process = new Process();

$task = function ($parameters) {
    echo "Task: order = {$parameters['order']}\n";

    sleep(10);

    $message = sprintf("This is a child process, my pid: %s, my ppid: %s\n", getmypid(), posix_getppid());

    echo $message;
};

// Second parameter sets number of tasks (by default = 1)
$process->addTask($task, 5);

$process->start();