<?php

namespace App\IPC;

use App\Exceptions\FileBufferException;

class FileBuffer implements IPCInterface
{
    private $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function open()
    {
       return $this;
    }

    public function checkIsFileExists()
    {
        if (!is_file($this->path)) {
            throw new FileBufferException(
                sprintf("File '%s' is not found.", $this->path)
            );
        }
    }

    public function checkIsFileReadable()
    {
        $this->checkIsFileExists();

        if (!is_readable($this->path)) {
            throw new FileBufferException(
                sprintf("File '%s' is not readable.", $this->path)
            );
        }
    }

    public function checkIsFileWritable()
    {
        $this->checkIsFileExists();

        if (!is_writable($this->path)) {
            throw new FileBufferException(
                sprintf("File '%s' is not writable.", $this->path)
            );
        }
    }

    private function isValidJson(string $strJson = null): bool 
    { 
        json_decode($strJson); 
        return (json_last_error() === JSON_ERROR_NONE); 
    }

    public function read(int $size): string
    {
        $this->checkIsFileReadable();

        $fileDescriptor = fopen($this->path, 'c+');

        if ($fileDescriptor === false) {
            return '';
        }

        $data = $this->readByDescriptor($fileDescriptor, $size);

        if (!$this->isValidJson($data)) {
            fclose($fileDescriptor);
            return '';
        }

        $data = json_decode($data, true);

        $isDataReadable = is_array($data) && 
                            isset($data['receiver']) && 
                              $data['receiver'] == posix_getpid();

        if (!$isDataReadable) {
            fclose($fileDescriptor);
            return '';
        }

        $data = isset($data['message']) ? $data['message'] : '';
        
        $this->clearByDescriptor($fileDescriptor);
        
        flock($fileDescriptor, LOCK_UN);
        fclose($fileDescriptor);

        return trim($data);
    }

    private function readByDescriptor($fileDescriptor, int $size): string
    {
        $data = "";
        
        if (is_resource($fileDescriptor)) {
            while (($buffer = fread($fileDescriptor, $size)) !== '') {
                $data .= $buffer;
            }
        }

        return $data;
    }

    private function clearByDescriptor($fileDescriptor, int $size = 0)
    {
        if (is_resource($fileDescriptor)) {
            rewind($fileDescriptor);
            ftruncate($fileDescriptor, $size);
        }
    }

    public function write(string $data)
    {
        $this->checkIsFileWritable();

        $fileDescriptor = fopen($this->path, 'c+');

        if ($fileDescriptor === false) {
            return false;
        }

        if (flock($fileDescriptor, LOCK_EX | LOCK_NB) === false) {
            fclose($fileDescriptor);
            return false;
        }

        $this->clearByDescriptor($fileDescriptor);

        fwrite($fileDescriptor, $data);
        fclose($fileDescriptor);     

        return true;
    }

    public function close()
    {
        return $this;
    }
}