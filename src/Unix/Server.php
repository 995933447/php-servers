<?php
namespace Bobby\Servers\Unix;

use Bobby\Servers\Contracts\ConnectionOrientedServerContract;
use Bobby\StreamEventLoop\LoopContract;

class Server extends ConnectionOrientedServerContract
{
    public function listen()
    {
        $this->server = stream_socket_server(
            "unix://{$this->serveSocket->getAddress()}",
            $errno,
            $error,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $this->serveSocket->getContext()
        );

        if (!$this->server) {
            throw new \RuntimeException($error, $errno);
        }

        $this->eventLoop->addLoopStream(LoopContract::READ_EVENT, $this->server, function () {
            $this->accept();
        });
    }
}