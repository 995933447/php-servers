<?php
namespace Bobby\Servers\Contracts;

use Bobby\Servers\Socket;
use Bobby\StreamEventLoop\LoopContract;

abstract class ServerContract
{
    protected $serveSocket;

    protected $config;

    protected $server;

    protected $eventLoop;

    public function __construct(SocketContract $serveSocket, ServerConfigContract $config, LoopContract $eventLoop)
    {
        $this->serveSocket = $serveSocket;
        $this->config = $config;
        $this->eventLoop = $eventLoop;
    }

    public function getEventLoop(): LoopContract
    {
        return $this->eventLoop;
    }

    abstract public function getConnections(): ?ConnectionPoolContract;

    abstract public function on(string $event, callable $listener);

    abstract public function pause();

    abstract public function resume();

    abstract public function close(ConnectionContract $connection, bool $force = false);

    abstract public function send($stream, string $message): bool;

    abstract public function listen();

    abstract public function getServeSocket(): SocketContract;
}