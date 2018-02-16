<?php

namespace App\IPC\UnixSockets;

use App\Exceptions\UnixSocketException;

class UnixSocketClient implements IPCInterface
{
    private $path;
    
    private $isNonBlocking;
    
    private $socket;
    
    public static $TYPE = PHP_BINARY_READ;

    public function __construct(string $path, bool $isNonBlocking = false)
    {
        $this->path = $path;
        $this->isNonBlocking = $isNonBlocking;
    }

    public function open()
    {
        $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);

        if (false === $this->socket) {
            throw new Exception($this->error());
        }

        if ($this->isNonBlocking && !@socket_set_nonblock($this->socket)) {
            throw new Exception($this->error($this->socket));
        }

        if (!@socket_connect($this->socket, $this->path)) {
            throw new Exception($this->error($this->socket));
        }

        return $this;
    }

    public function read(int $size): string
    {
        if (!is_resource($this->socket)) {
            throw new Exception("Unix socket is not inialized");
        }

        $buffer = "";

        while ($chunk = @socket_read($this->socket, $size, self::$TYPE)) {
            $buffer .= trim($chunk); 
        }
        
        return $buffer;
    }

    public function write($data)
    {
        if (!is_resource($this->socket)) {
            throw new Exception("Unix socket is not inialized");
        }

        @socket_write($this->socket, $data, strlen($data));

        return $this;
    }

    public function close()
    {
        $this->socket = null;
        @socket_shutdown($this->socket);
        @socket_close($this->socket);

        return $this;
    }

    private function error($socket = null): string
    {
        $lastError = is_null($socket) 
                     ? socket_last_error() 
                     : socket_last_error($socket);
        
        return socket_strerror($lastError);
    }
}