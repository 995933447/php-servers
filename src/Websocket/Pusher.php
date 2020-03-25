<?php
namespace Bobby\Servers\Websocket;

use Bobby\Servers\Connection;

class Pusher
{
    protected $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function ping(Connection $connection)
    {
        $this->server->send($connection, Frame::encode(OpcodeEnum::PING, ''));
    }

    public function pong(Connection $connection)
    {
        $this->server->send($connection, Frame::encode(OpcodeEnum::PONG, ''));
    }

    public function pushString(Connection $connection, string $message)
    {
        $this->server->send($connection, Frame::encode(OpcodeEnum::TEXT, $message));
    }

    public function pushFile(Connection $connection, string $message)
    {
        $this->server->send($connection, Frame::encode(OpcodeEnum::BINARY, $message));
    }

    public function notifyClose(Connection $connection)
    {
        $this->server->send($connection, Frame::encode(OpcodeEnum::OUT_CONNECT, ''));
    }
}