<?php

namespace App\IPC;

interface IPCInterface
{
    public function open();
    public function read(int $size): string;
    public function write(string $data);
    public function close();
}