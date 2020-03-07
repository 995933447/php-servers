<?php
namespace Bobby\Network\Contracts;

interface SendingBufferPoolContract
{
    public function add($stream, string $buffer);

    public function get($stream): string;

    public function exist($stream);

    public function remove($stream);

    public function set($stream, string $buffer);
}