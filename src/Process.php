<?php

namespace App;

use App\Reflection\CallableReflection;

class Process
{
    /**
     * @var boolean The current state of main process
     */
    private $isActive = false;
    
    /**
     * @var boolean Ability to complete the process after done all tasks 
     */
    private $isCompletable = false;
    
    /**
     * @var array The ids of forked child processes
     */
    private $childProcessIds = [];
    
    /**
     * @var Communicator|null Object for communicate between main and child processes
     */
    private $communicator;

    /**
     * @var array Tasks for execution by child processes
     */
    private $tasks = [];

    /**
     * @static int Delay between processing of child processes (ms)
     */
    public static $DELAY = 100;

    /**
     * Process constructor
     *
     *  @param boolean $isCompletable Ability to complete the process after done all tasks
     */
    public function __construct(bool $isCompletable = true)
    {
        $this->isCompletable = $isCompletable;

        $this->addSignalHandler(SIGHUP, [$this, "stop"]);

        $this->addSignalHandler(SIGCHLD, [$this, "handleChildProcesses"]);
    }

    /**
     * Runs process
     */
    public function start()
    {
        $this->isActive = true;

        foreach ($this->tasks as $order => $task) {
            $this->fork($task, $order);
        }

        while (true) {
            if ($this->communicator) {
                $this->communicator->receiveAndHandle();
            }

            $this->signalDispatch();

            if (!$this->isActive && empty($this->childProcessIds)) {
                break;
            }

            usleep(self::$DELAY);
        }
    }

    /**
     * Stops process
     */
    public function stop()
    {
        $this->isActive = false;

        if ($this->communicator) {
            $this->communicator->close();
            $this->communicator = null;
        }
    }

    /**
     * Forks processes
     *
     * When task (Callable object) is started, the number of the 
     * task (order) is transferred to the input as an array of parameters
     *
     * @param Callable $task Callable object (task)
     * @param int $order Order of Callable object (task)
     */
    private function fork(Callable $task, int $order)
    {
        $childProcessId = pcntl_fork();

        if ($childProcessId > 0) {
            $this->childProcessIds[] = $childProcessId;
            return;
        }

        $task(['order' => $order]);

        exit(0);
    }

    /**
     * Handles all child processes
     *
     * Reads exit statuses of all child processes. If current process 
     * is completable then after done all child processes it will be stoped 
     */
    public function handleChildProcesses()
    {
        foreach ($this->childProcessIds as $key => $childProcessId) {
            $result = pcntl_waitpid($childProcessId, $status, WNOHANG);

            if ($result == -1 || $result > 0) {
                unset($this->childProcessIds[$key]);
            }
        }

        if ($this->isCompletable && empty($this->childProcessIds)) {
            $this->stop();
        }
    }

    /**
     * Adds signal handler
     *
     * @param int $SIGNAL The signal number 
     * @param Callable $signalHandler Signal handler
     */
    public function addSignalHandler(int $SIGNAL, Callable $signalHandler)
    {
        pcntl_signal($SIGNAL, $signalHandler);
    }

    /**
     * Dispatches all signals
     *
     */
    private function signalDispatch() 
    {
        pcntl_signal_dispatch();
    }

    /**
     * Adds Callable object (task) to the current process 
     *
     * @param boolean $isCompletable The flag for stopping the processing of child processes
     * @param boolean $isCompletable The flag for stopping the processing of child processes
     *
     * @throws Exception When the current process is running or when given incorrect number of tasks
     */
    public function addTask(Callable $task, int $numberOfTasks = 1)
    {
        if ($this->isActive) {
            throw new Exception('');
        }

        if ($numberOfTasks < 1) {
            throw new Exception('');
        }

        $this->tasks = array_merge(
            $this->tasks,
            array_fill(0, $numberOfTasks, $task)
        );
    }

    /**
     * Binds parameters to Callable object
     *
     * @static Utility method
     * 
     * @param Callable $task Callable object
     * @param array $bindingParameters The parameters for binding to the given Callable object
     *
     * @return Callable Wrapped Callable object with binded parameters
     */
    public static function bindParameters(Callable $task, array $bindingParameters): Callable
    {
        $reflection = CallableReflection::create($task);

        $numberOfParameters = $reflection->getNumberOfParameters(); 
        $numberOfParameters = $numberOfParameters > 0 ? $numberOfParameters : 1;
        
        $arguments = array_fill(0, $numberOfParameters, null);
        $arguments[0] = $bindingParameters;

        return function (array $parameters = []) use ($task, $arguments) {
            $arguments[0] = array_merge($arguments[0], $parameters);
            return call_user_func_array($task, $arguments);
        };
    }

    /**
     * Sets IPC communicator to communicate between main process and child processes
     *
     *  @param Communicator $communicator IPC communicator to communicate between main process and child processes
     */
    public function setCommunicator(Communicator $communicator)
    {
        if ($this->communicator) {
            $this->communicator->close();
        }

        $this->communicator = $communicator;
        $this->communicator->open();
    }

    /**
     * Gets IPC communicator
     *
     *  @return Communicator
     */
    public function getCommunicator()
    {
        return $this->communicator;
    }
}