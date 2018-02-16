<?php

namespace App\IPC;

class Communicator
{
    private $ipc;
    
    private $handler;

    public function __construct(IPCInterface $ipc)
    {
        $this->ipc = $ipc;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function setHandler(Callable $handler)
    {
        $this->handler = $handler;
    }

    public function receiveAndHandle(int $size = 2048)
    {
        if ($this->handler && is_callable($this->handler)) {
            $data = $this->ipc->read($size);
            
            if ($data) {
                ($this->handler)($data);
            }
        }
    }

    public function receive(int $size = 2048)
    {
        return $this->ipc->read($size);
    }

    public function transmit($data)
    {
        $this->ipc->write($data);
    }

    public function open()
    {
        $this->ipc->open();
    }

    public function close()
    {
        $this->ipc->close();
    }
}