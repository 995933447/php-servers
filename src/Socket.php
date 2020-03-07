<?php
namespace Bobby\Network;

use Bobby\Network\Contracts\SocketContract;
use Bobby\Network\Utils\InvalidArgumentException;

class Socket implements SocketContract
{
    protected $address;

    protected $transport;

    protected $context;

    protected $isOpenedSsl = false;

    final public function __construct(string $listen, array $context = [])
    {
        $this->parseListen($listen);
        $this->createContext($context);
    }

    protected function parseListen(string $listen)
    {
        if (($transportPosition = strpos($listen, '://')) === false) {
            throw InvalidArgumentException::defaultThrow();
        }

        if (strlen($listen) <= $transportPosition + 3) {
            throw InvalidArgumentException::defaultThrow();
        }

        $this->transport = substr($listen, 0, $transportPosition);
        $this->address = substr($listen, $transportPosition + 3);
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

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function getContext()
    {
        return $this->context;
    }
}