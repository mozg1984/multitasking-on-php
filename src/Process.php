<?php

namespace App;

use App\IPC\Communicator;
use App\Reflection\CallableReflection;
use App\Exceptions\ProcessException;

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
     * @var array Buffer to store messages from child processes
     */
    private $buffer = [];

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

        while (true) {
            $this->executeTasks();

            if ($this->communicator) {
                $this->communicator->receiveAndHandle();
            }

            $this->signalDispatch();

            if (!$this->isActive && !$this->hasChildProcesses()) {
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

    private function executeTasks()
    {
        if (!$this->hasTasks()) {
            return;
        }

        while ($this->hasTasks()) {
            $task = $this->tasks[0];

            if ($task['is_expecting'] && $this->hasChildProcesses()) {
                break;
            }

            $task = array_shift($this->tasks);

            for ($i = 0; $i < $task['number_of_tasks']; $i++) {
                $this->fork($task['task'], $i);
            }
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

        if ($this->isCompletable && !$this->hasTasks() && !$this->hasChildProcesses()) {
            $this->stop();
        }
    }

    public function hasChildProcesses(): bool
    {
        return !empty($this->childProcessIds);
    }

    public function hasTasks(): bool
    {
        return !empty($this->tasks);
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
     * Clears buffer
     */
    public function clearBuffer(): array
    {
        $this->buffer = [];
        
        return $this;
    }

    /**
     * Gets buffer
     */
    public function getBuffer(): array
    {
        return $this->buffer;
    }

    /**
     * Adds string message to buffer
     */
    public function addToBuffer(string $message)
    {
        $this->buffer[] = $message;
        
        return $this;
    }

    /**
     * Adds Callable object (task) to the current process 
     *
     * @param Callable $task Callable object (task)
     * @param boolean $numberOfTasks Number of processes to perform task
     * @param boolean $isExpecting Sets expecting mode to task
     *
     * @throws Exception When given incorrect number of tasks
     */
    public function addTask(Callable $task, int $numberOfTasks = 1, bool $isExpecting = false)
    {
        if ($numberOfTasks < 1) {
            throw new ProcessException('Given incorrect number of tasks');
        }

        array_push($this->tasks, [
            'task' => $task,
            'number_of_tasks' => $numberOfTasks,
            'is_expecting' => $isExpecting
        ]);
    }

    /**
     * Adds Callable object (task) to the current process 
     *
     * @param Callable $task Callable object (task)
     * @param boolean $numberOfTasks Number of processes to perform task
     *
     * @throws Exception When given incorrect number of tasks
     */
    public function addExpectingTask(Callable $task, int $numberOfTasks = 1)
    {
        $this->addTask($task, $numberOfTasks, true);
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