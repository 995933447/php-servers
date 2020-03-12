<?php
namespace Bobby\Servers\Websocket;

use Bobby\Servers\Contracts\SocketContract;
use Bobby\Servers\Http\Server as HttpServer;
use Bobby\Servers\ServerConfig;
use Bobby\StreamEventLoop\LoopContract;

class Server extends HttpServer
{
    public function __construct(SocketContract $serveSocket, ServerConfig $config, LoopContract $eventLoop)
    {
        parent::__construct($serveSocket, $config, $eventLoop);
    }
}