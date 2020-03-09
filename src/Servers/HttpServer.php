<?php
namespace Bobby\Network;

use Bobby\Network\Contracts\ConnectionContract;
use Bobby\Network\Contracts\SocketContract;
use Bobby\Network\Servers\TcpServer;
use Bobby\ServerNetworkProtocol\Http\Parser;
use Bobby\ServerNetworkProtocol\Http\Request;
use Bobby\StreamEventLoop\LoopContract;

class HttpServer extends TcpServer
{
    const REQUEST_EVENT = 'request';

    protected $allowEvents = [self::REQUEST_EVENT, self::ERROR_EVENT, self::CLOSE_EVENT];

    public function __construct(SocketContract $serveSocket, ServerConfig $config, LoopContract $eventLoop)
    {
        parent::__construct($serveSocket, $config, $eventLoop);

        $this->on(self::CONNECT_EVENT, function (HttpServer $server, ConnectionContract $connection) {
            $this->upgradeConnectionProtocol($connection);
        });

        $this->on(self::RECEIVE_EVENT, function (HttpServer $server, Connection $connection, Request $request) {
            $this->emitOnRequest($request);
        });
    }

    protected function emitOnRequest(Request $request)
    {
        $this->eventHandler->trigger(static::REQUEST_EVENT, $this, $request);
    }

    protected function upgradeConnectionProtocol(ConnectionContract $connection)
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