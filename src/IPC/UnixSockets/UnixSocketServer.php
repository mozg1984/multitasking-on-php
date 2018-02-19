<?php

namespace App\IPC\UnixSockets;

use App\IPC\IPCInterface;
use App\Exceptions\UnixSocketException;

class UnixSocketServer implements IPCInterface
{
    private $socket;
    
    private $clients = [];
    
    public static $TYPE = PHP_BINARY_READ;
    
    public static $BACKLOG = 10;

    public function __construct(string $path, bool $isNonBlocking = false)
    {
        $this->path = $path;
        $this->isNonBlocking = $isNonBlocking;
    }

    public function open()
    {
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if (false === $this->socket) {
            throw new UnixSocketException($this->error());
        }

        if ($this->isNonBlocking && !@socket_set_nonblock($this->socket)) {
            throw new UnixSocketException($this->error($this->socket));
        }

        if (!@socket_bind($this->socket, $this->path)) {
            throw new UnixSocketException($this->error($this->socket));
        }

        if (!@socket_listen($this->socket, self::$BACKLOG)) {
            throw new UnixSocketException($this->error($this->socket));
        }

        return $this;
    }

    private function readClientSockets(int $size = 2048): array
    {
        $responses = [];
        
        foreach ($this->clients as $clientSocket) {
            if (!is_resource($clientSocket)) {
                continue;
            }

            $response = $this->readClientSocket($clientSocket, $size);

            if ($response) {
                $responses[] = $response;
            }
        }

        return $responses;
    }

    private function readClientSocket($clientSocket, int $size): string
    {
        $buffer = "";

        while ($chunk = @socket_read($clientSocket, $size, self::$TYPE)) {
            $buffer .= trim($chunk); 
        }
        
        return $buffer;
    }

    public function read(int $size): string
    {
        if (!is_resource($this->socket)) {
            throw new UnixSocketException("Unix socket is not inialized");
        }

        while (true) {
            $clientSocket = @socket_accept($this->socket);
        
            if (!is_resource($clientSocket)) {
                break;
            }

            @socket_set_nonblock($clientSocket);
            $this->clients[] = $clientSocket;
        }

        $responses = $this->readClientSockets($size);

        return !empty($responses) 
               ? json_encode($responses)
               : false;  
    }

    public function write(string $data)
    {
        if (!is_resource($this->socket)) {
            throw new UnixSocketException("Unix socket is not inialized");
        }

        foreach ($this->clients as $clientSocket) {
            socket_write($clientSocket, $data, mb_strlen($data));
        }

        return $this;
    }

    public function close()
    {
        $this->socket = null;
        $this->clients = [];
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