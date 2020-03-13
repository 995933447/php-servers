<?php
namespace Bobby\Servers\Udp;

use Bobby\Servers\Contracts\ConnectionContract;
use Bobby\Servers\Contracts\ConnectionPoolContract;
use Bobby\Servers\Contracts\ServerConfigContract;
use Bobby\Servers\Contracts\ServerContract;
use Bobby\Servers\Contracts\SocketContract;
use Bobby\Servers\Exceptions\InvalidArgumentException;
use Bobby\Servers\Exceptions\SocketReadFailedException;
use Bobby\Servers\Utils\EventHandler;
use Bobby\StreamEventLoop\LoopContract;

class Server extends ServerContract
{
    const RECEIVE_EVENT = 'receive';

    const ERROR_EVENT = 'error';

    const MAX_PACKAGE_SIZE = 65535;

    protected $eventHandler;

    protected $allowEvents = [self::RECEIVE_EVENT, self::ERROR_EVENT];

    public function __construct(SocketContract $serveSocket, ServerConfigContract $config, LoopContract $eventLoop)
    {
        parent::__construct($serveSocket, $config, $eventLoop);

        $this->eventHandler = new EventHandler();
    }

    public function on(string $event, callable $listener)
    {
        if (!in_array($event, $this->allowEvents)) {
            throw InvalidArgumentException::defaultThrow("First event can not allow set.");
        }

        $this->eventHandler->register($event, $listener);
    }

    public function pause()
    {
        $this->eventLoop->removeLoopStream(LoopContract::READ_EVENT, $this->server);
    }

    public function resume()
    {
        $this->eventLoop->addLoopStream(LoopContract::READ_EVENT, $this->server, function () {
            $this->receive();
        });
    }

    public function sendTo(string $address, string $message)
    {
        return stream_socket_sendto($this->server, $message, 0, $address);
    }

    function listen()
    {
        stream_context_set_option($this->serveSocket->getContext(), 'socket', 'so_reuseport', 1);

        $this->server = stream_socket_server(
            "udp://" . $this->serveSocket->getAddress(),
            $errno,
            $error,
            STREAM_SERVER_BIND,
            $this->serveSocket->getContext()
        );

        if ($this->server === false) {
            throw new \RuntimeException($error, $errno);
        }

        stream_set_blocking($this->server, false);

        $this->eventLoop->addLoopStream(LoopContract::READ_EVENT, $this->server, function ($socket) {
            $this->receive();
        });
    }

    protected function receive()
    {
        $readException = null;
        set_error_handler(function ($errno, $error, $file, $line) use ($readException) {
            $readException = new SocketReadFailedException($error, $errno, $file, $line);
        });

        $message = stream_socket_recvfrom($this->server, static::MAX_PACKAGE_SIZE, 0, $address);

        restore_error_handler();

        if (!is_null($readException)) {
            $this->emitOnError($readException);
        } else {
            $this->emitOnReceive($address, $message);
        }
    }

    protected function emitOnReceive(string $address, string $message)
    {
        $this->eventHandler->trigger(static::RECEIVE_EVENT, $this, $address, $message);
    }

    protected function emitOnError(\Throwable $exception)
    {
        $this->eventHandler->trigger(static::ERROR_EVENT, $this, $this->server, $exception);
    }
}