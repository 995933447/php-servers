<?php
namespace Bobby\Servers\Http;

use Bobby\Servers\Http\ClearAliveConnectionTimerPool;
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

    const DEFAULT_KEEP_ALIVE_TIMEOUT = 60;

    protected $clearConnectionTimerPool;

    public function __construct(SocketContract $serveSocket, ServerConfig $config, LoopContract $eventLoop)
    {
        parent::__construct($serveSocket, $config, $eventLoop);

        $this->setHttpServerMustListenEvent();

        $this->resetAllowListenEvents();
    }

    protected function getClearConnectionTimerPool(): ClearAliveConnectionTimerPool
    {
        if (is_null($this->clearConnectionTimerPool)) {
            $this->clearConnectionTimerPool = new ClearAliveConnectionTimerPool();
        }
        return $this->clearConnectionTimerPool;
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
                (!isset($request->header['connection']) || $request->header['connection'] !== 'keep-alive') &&
                (!isset($request->header['Connection']) || $request->header['Connection'] !== 'keep-alive')
            ) {
                $this->close($connection);
            } else {
                $keepAliveTimeout = $this->config->serveOptions['keep_alive_timeout']?? static::DEFAULT_KEEP_ALIVE_TIMEOUT;

                $timerId = $this->getEventLoop()->addAfter($keepAliveTimeout, function () use ($connection, $keepAliveTimeout) {
                    $this->getEventLoop()->removeLoopStream(LoopContract::READ_EVENT, $connection->exportStream());
                    $this->getClearConnectionTimerPool()->remove($connection);
                });

                if ($this->getClearConnectionTimerPool()->exists($connection)) {
                    $this->getEventLoop()->removeTimer($this->getClearConnectionTimerPool()->get($connection));
                }

                $this->getClearConnectionTimerPool()->save($connection, $timerId);
            }

            foreach ($request->uploadedFileTempNames as $tempFileName) {
                if (file_exists($tempFileName)) {
                    unset($tempFileName);
                }
            }
        });

        $this->on(self::CONNECT_FULL_EVENT, function (Server $server, Connection $connection) {
            $response = new Response($this, $connection);
            $response->status(ResponseCodeEnum::HTTP_SERVICE_UNAVAILABLE)->end();
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