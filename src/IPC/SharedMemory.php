<?php

namespace App\IPC;

/**
 *
 */
class SharedMemory implements IPCInterface
{
    private $segmentId;// system segment id
    
    private $permission; // permission
    
    public static $ENCODING = 'UTF-8';

    public function __construct(int $id = null, int $permission = 0644)
    {
        $this->segmentId = $id;
        $this->permission = $permission;
        $this->release();
    }

    public function open()
    {
        return $this;  
    }

    public function release()
    {
        $sharedMemoryId = @shmop_open($this->segmentId, "a", $this->permission, 0);

        if ($sharedMemoryId) {
            @shmop_delete($sharedMemoryId);
            @shmop_close($sharedMemoryId);
        }
    }

    private function isValidJson(string $strJson = null): bool
    { 
        json_decode($strJson); 
        return (json_last_error() === JSON_ERROR_NONE); 
    }

    public function write(string $data)
    {
        $dataSize = mb_strlen($data, self::$ENCODING);
        $sharedMemoryId = @shmop_open($this->segmentId, "n", $this->permission, $dataSize);

        if (!$sharedMemoryId) {
            return false;
        }

        $written = @shmop_write($sharedMemoryId, $data, 0);
        @shmop_close($sharedMemoryId);

        if ($written != $dataSize) {
            return false;
        }      

        return true;
    }

    public function read(int $size = 0): string
    {
        $sharedMemoryId = @shmop_open($this->segmentId, "a", $this->permission, 0);

        if (!$sharedMemoryId) {
            return '';
        }

        $data = @shmop_read($sharedMemoryId, 0, @shmop_size($sharedMemoryId));

        if (!$this->isValidJson($data)) {
            @shmop_close($sharedMemoryId);
            return '';
        }

        $data = json_decode($data, true);

        $isDataReadable = is_array($data) && 
                            isset($data['receiver']) && 
                              $data['receiver'] == posix_getpid();

        if (!$isDataReadable) {
            @shmop_close($sharedMemoryId);
            return '';
        }

        $data = isset($data['message']) ? $data['message'] : '';
        
        @shmop_delete($sharedMemoryId);
        @shmop_close($sharedMemoryId);

        return trim($data);
    }

    public function close()
    {
        return $this;    
    }
}