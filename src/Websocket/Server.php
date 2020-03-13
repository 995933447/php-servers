<?php
namespace Bobby\Servers\Websocket;

use Bobby\ServerNetworkProtocol\Http\Request;
use Bobby\ServerNetworkProtocol\Websocket\Frame;
use Bobby\ServerNetworkProtocol\Websocket\Parser;
use Bobby\Servers\Connection;
use Bobby\Servers\ConnectionPool;
use Bobby\Servers\Contracts\ConnectionContract;
use Bobby\Servers\Contracts\SocketContract;
use Bobby\Servers\Http\Server as HttpServer;
use Bobby\Servers\ServerConfig;
use Bobby\StreamEventLoop\LoopContract;

class Server extends HttpServer
{
    const MESSAGE_EVENT = 'message';

    const OPEN_EVENT = 'open';

    protected $shackedConnections;

    protected $pusher;

    public function __construct(SocketContract $serveSocket, ServerConfig $config, LoopContract $eventLoop)
    {
        $this->shackedConnections = new ConnectionPool();

        $this->pusher = new Pusher($this);

        parent::__construct($serveSocket, $config, $eventLoop);
    }

    public function getPusher(): Pusher
    {
        return $this->pusher;
    }

    public function getShakedConnections(): ConnectionPool
    {
        return $this->shackedConnections;
    }

    protected function resetAllowListenEvents()
    {
        $this->allowEvents = [static::OPEN_EVENT, static::MESSAGE_EVENT, static::REQUEST_EVENT, self::ERROR_EVENT, self::CLOSE_EVENT];
    }

    protected function setHttpServerMustListenEvent()
    {
        parent::setHttpServerMustListenEvent();

        $this->on(self::RECEIVE_EVENT, function (Server $server, Connection $connection, $data) {
            if ($data instanceof Request) {
                $this->dealHttpRequest($connection, $data);
            } else {
                $this->dealWebsocketRequest($connection, $data);
            }
        });
    }

    protected function dealWebsocketRequest(Connection $connection, Frame $frame)
    {
        $this->emitOnMessage($connection, $frame);
    }

    protected function dealHttpRequest(Connection $connection, Request $request)
    {
        $request->server['REMOTE_ADDR'] = substr($remoteAddress = $connection->getRemoteAddress(), 0, strpos($remoteAddress, ':'));
        $request->server['REMOTE_PORT'] = (int)substr($remoteAddress, strpos(':', $remoteAddress));

        $connectionHeader = $request->header['Connection']?: '';
        if (empty($connectionHeader)) {
            $connectionHeader = $request->header['connection']?: '';
        }

        if ($connectionHeader === 'Upgrade') {
            $upgradeHeader = $request->header['Upgrade']?: '';
            if (empty($upgradeHeader)) {
                $upgradeHeader = $request->header['upgrade']?: '';
            }

            if ($upgradeHeader === 'websocket' && isset($request->header['Sec-WebSocket-Key'])) {
                return $this->upgradeConnectionProtocolToWebsocket($connection, $request);
            }
        }

        $this->emitOnRequest($connection, $request);

        if ($connectionHeader === 'close') {
            $this->close($connection);
        }

        foreach ($request->uploadedFileTempNames as $tempFileName) {
            if (file_exists($tempFileName)) {
                unset($tempFileName);
            }
        }
    }

    protected function upgradeConnectionProtocolToWebsocket(Connection $connection, Request $request)
    {
        $newSecurityKey = base64_encode(sha1($request->header['Sec-WebSocket-Key'] . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

        $upgradeHeader = "HTTP/1.1 101 Switching Protocols\r\n";
        $upgradeHeader .= "Upgrade: websocket\r\n";
        $upgradeHeader .= "Sec-WebSocket-Version: 13\r\n";
        $upgradeHeader .= "Connection: Upgrade\r\n";
        $upgradeHeader .= "Sec-WebSocket-Accept: $newSecurityKey\r\n\r\n";

        $this->send($connection->exportStream(), $upgradeHeader);

        $connection->setProtocolParser(new Parser($this->config->protocolOptions));

        $this->shackedConnections->add($connection);

        $this->emitOnOpen($connection, $request);
    }

    public function close(ConnectionContract $connection, bool $force = false)
    {
        parent::close($connection, $force);
        $this->shackedConnections->remove($connection);
    }

    protected function emitOnMessage(Connection $connection, Frame $frame)
    {
        $this->eventHandler->trigger(static::MESSAGE_EVENT, $this, $connection, $frame);
    }

    protected function emitOnOpen(Connection $connection, Request $request)
    {
        $this->eventHandler->trigger(static::OPEN_EVENT, $this, $connection, $request);
    }
}