<?php
namespace Bobby\Servers;

use Bobby\Servers\Contracts\SocketContract;

class Socket implements SocketContract
{
    protected $address;

    protected $context;

    protected $isOpenedSsl = false;

    final public function __construct(string $address, array $context = [])
    {
        $this->address = $address;
        $this->createContext($context);
    }

    protected function createContext($context)
    {
        if (isset($context['ssl'])) {
            $this->isOpenedSsl = true;
        }
        $this->context = stream_context_create($context);
    }

    public function isOpenedSsl()
    {
        return $this->isOpenedSsl;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getContext()
    {
        return $this->context;
    }
}