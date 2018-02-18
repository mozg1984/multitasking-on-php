<?php
/**
 * Creates one main process and sets specified task for 5 child processes.
 * Other way to specify task. By this way you can divide your logic to specific methods.
 * It will improve the support of your logic in future.
 */

$process = new Process();

// Second parameter sets number of tasks (by default = 1)
$process->addTask(new class extends Task {
    public function run($parameters) {
        // Your task logic...
    }
}, 5);

$process->start();