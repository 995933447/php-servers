<?php
namespace Bobby\Servers;

use Bobby\Servers\Contracts\SendingBufferPoolContract;

class SendingBufferPool implements SendingBufferPoolContract
{
    protected $pool = [];

    public function add($stream, string $buffer)
    {
        $this->pool[(int)$stream] = $this->get($stream) . $buffer;
    }

    public function get($stream): string
    {
        return $this->pool[(int)$stream]?? '';
    }

    public function exist($stream)
    {
        return isset($this->pool[(int)$stream]);
    }

    public function remove($stream)
    {
        unset($this->pool[(int)$stream]);
    }

    public function set($stream, string $buffer)
    {
        $this->pool[(int)$stream] = $buffer;
    }
}