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

        $this->setHttpServerMustListenEvent();

        $this->resetAllowListenEvents();
    }

    protected function setHttpServerMustListenEvent()
    {
        $this->on(self::CONNECT_EVENT, function (Server $server, ConnectionContract $connection) {
            $this->upgradeConnectionProtocolToHttp($connection);
        });

        $this->on(self::RECEIVE_EVENT, function (Server $server, Connection $connection, Request $request) {
            $request->server['REMOTE_ADDR'] = substr($remoteAddress = $connection->getRemoteAddress(), 0, strpos($remoteAddress, ':'));
            $request->server['REMOTE_PORT'] = (int)substr($remoteAddress, strpos(':', $remoteAddress));

            $this->emitOnRequest($connection, $request);

            if (
                (isset($request->header['connection']) && $request->header['connection'] === 'close') ||
                (isset($request->header['Connection']) && $request->header['Connection'] === 'close')
            ) {
                $this->close($connection);
            }

            foreach ($request->uploadedFileTempNames as $tempFileName) {
                if (file_exists($tempFileName)) {
                    unset($tempFileName);
                }
            }
        });
    }

    protected function resetAllowListenEvents()
    {
        $this->allowEvents = [static::REQUEST_EVENT, self::ERROR_EVENT, self::CLOSE_EVENT];
    }

    protected function emitOnRequest(Connection $connection, Request $request)
    {
        $this->eventHandler->trigger(static::REQUEST_EVENT, $this, $request, new Response($this, $connection));
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