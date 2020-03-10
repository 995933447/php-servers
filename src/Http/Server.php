<?php
namespace Bobby\Servers\Http;

use Bobby\Servers\Contracts\ConnectionContract;
use Bobby\Servers\Contracts\SocketContract;
use Bobby\Servers\Tcp\Server as TcpServer;
use Bobby\ServerNetworkProtocol\Http\Parser;
use Bobby\ServerNetworkProtocol\Http\Request;
use Bobby\StreamEventLoop\LoopContract;
use Bobby\Servers\ServerConfig;
use Bobby\Servers\Connection;

class Server extends TcpServer
{
    const REQUEST_EVENT = 'request';

    public function __construct(SocketContract $serveSocket, ServerConfig $config, LoopContract $eventLoop)
    {
        parent::__construct($serveSocket, $config, $eventLoop);

        $this->on(self::CONNECT_EVENT, function (Server $server, ConnectionContract $connection) {
            $this->upgradeConnectionProtocolToHttp($connection);
        });

        $this->on(self::RECEIVE_EVENT, function (Server $server, Connection $connection, Request $request) {
            $this->emitOnRequest($connection, $request);
        });

        $this->resetOnAllowEvents();
    }

    protected function resetOnAllowEvents()
    {
        $this->allowEvents = [static::REQUEST_EVENT, self::ERROR_EVENT, self::CLOSE_EVENT];
    }

    protected function emitOnRequest(Connection $connection, Request $request)
    {
        $this->eventHandler->trigger(static::REQUEST_EVENT, $this, $connection, $request, new Response());
    }

    protected function upgradeConnectionProtocolToHttp(ConnectionContract $connection)
    {
        if ($connection->isClosed()) {
            $this->close($connection);
            return;
        }

        $upgradedConnection = new Connection($connection->exportStream(), $connection->getRemoteAddress(), new Parser($this->config->protocolOptions));
        if ($connection->isPaused()) {
            $upgradedConnection->pause();
        }
        if ($connection->isReadyClose()) {
            $upgradedConnection->readyClose();
        }
        if ($connection->isOpenedSsl()) {
            $upgradedConnection->openedSsl();
        }
        $upgradedConnection->getProtocolParser()->input($connection->getReceivedBuffer());

        $this->connections->remove($connection);
        $this->connections->add($upgradedConnection);
    }
}