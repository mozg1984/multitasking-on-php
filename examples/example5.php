<?php
/**
 * In this example communicator uses shared memory for IPC
 */

// Creates main process
$process = new Process();

//Creates concrete IPC method for main process
$sharedMemory = new SharedMemory(posix_getpid());

// Creates communicator for main process
$communicator = new Communicator($sharedMemory);

// Specify handler to handle messages from IPC
$communicator->setHandler(
    function ($message) {
        echo "Received message from client:\n";
        var_dump($message);
    }
);

// Sets communicator to main process
$process->setCommunicator($communicator);

// Creates communicator for child process
$communicator = new Communicator($sharedMemory);

// Adds task
$process->addTask(function ($parameters) {
    echo "Task: order = {$parameters['order']}\n";

    $parameters['Communicator']->open();

    sleep(15);

    $message = sprintf(
        "This is a child process, my pid: %s, my ppid: %s\n",
        getmypid(),
        posix_getppid()
    );

    $parameters['Communicator']->transmit($message);
    $parameters['Communicator']->close();

}, ['Communicator' => $communicator]);

// Start main process
$process->start();