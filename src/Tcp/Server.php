<?php
namespace Bobby\Servers\Tcp;

use Bobby\Servers\Contracts\ConnectionOrientedServerContract;
use Bobby\StreamEventLoop\LoopContract;

class Server extends ConnectionOrientedServerContract
{
    public function listen()
    {
        stream_context_set_option($this->serveSocket->getContext(), 'socket', 'so_reuseport', 1);

        $this->server = stream_socket_server(
            "tcp://{$this->serveSocket->getAddress()}",
            $errno,
            $error,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $this->serveSocket->getContext()
        );

        if ($this->server === false) {
            throw new \RuntimeException($error, $errno);
        }

        $socket = socket_import_stream($this->server);
        socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);

        $this->eventLoop->addLoopStream(LoopContract::READ_EVENT, $this->server, function () {
            $this->accept();
        });
    }
}