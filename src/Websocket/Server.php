<?php
namespace Bobby\Servers\Websocket;

use Bobby\ServerNetworkProtocol\Http\Request;
use Bobby\ServerNetworkProtocol\Websocket\Frame;
use Bobby\ServerNetworkProtocol\Websocket\Parser;
use Bobby\Servers\Connection;
use Bobby\Servers\ConnectionPool;
use Bobby\Servers\Contracts\ConnectionContract;
use Bobby\Servers\Contracts\ServerConfigContract;
use Bobby\Servers\Contracts\SocketContract;
use Bobby\Servers\Http\Server as HttpServer;
use Bobby\StreamEventLoop\LoopContract;
use Bobby\ServerNetworkProtocol\Websocket\OpcodeEnum;

class Server extends HttpServer
{
    const MESSAGE_EVENT = 'message';

    const OPEN_EVENT = 'open';

    const PING_EVENT = 'ping';

    const PONG_EVENT = 'pong';

    protected $shookConnections;

    protected $pusher;

    public function __construct(SocketContract $serveSocket, ServerConfigContract $config, LoopContract $eventLoop)
    {
        $this->shookConnections = new ConnectionPool();

        $this->pusher = new Pusher($this);

        parent::__construct($serveSocket, $config, $eventLoop);
    }

    public function getPusher(): Pusher
    {
        return $this->pusher;
    }

    public function getShookConnections(): ConnectionPool
    {
        return $this->shookConnections;
    }

    protected function resetAllowListenEvents()
    {
        $this->allowEvents = [static::OPEN_EVENT, self::PING_EVENT, self::PONG_EVENT, static::MESSAGE_EVENT, static::REQUEST_EVENT, self::ERROR_EVENT, self::CLOSE_EVENT];
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
        switch ($frame->opcode) {
            case OpcodeEnum::PING:
                if (!$this->eventHandler->exist(static::PING_EVENT)) {
                    $this->pusher->pong($connection);
                } else {
                    $this->emitOnPing($connection);
                }
                break;
            case OpcodeEnum::OUT_CONNECT:
                $this->close($connection);
                $this->emitOnClose($connection);
                break;
            case OpcodeEnum::PONG:
                $this->emitOnPong($connection);
                break;
            case OpcodeEnum::BINARY:
            case OpcodeEnum::TEXT:
            case OpcodeEnum::SEGMENT:
                $this->emitOnMessage($connection, $frame);
        }
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

        $this->send($connection, $upgradeHeader);

        $connection->setProtocolParser(new Parser($this->config->protocolOptions));

        $this->shookConnections->add($connection);

        $this->emitOnOpen($connection, $request);
    }

    public function close(ConnectionContract $connection, bool $force = false)
    {
        parent::close($connection, $force);
        $this->shookConnections->remove($connection);
    }

    protected function emitOnMessage(Connection $connection, Frame $frame)
    {
        $this->eventHandler->trigger(static::MESSAGE_EVENT, $this, $connection, $frame);
    }

    protected function emitOnOpen(Connection $connection, Request $request)
    {
        $this->eventHandler->trigger(static::OPEN_EVENT, $this, $connection, $request);
    }

    protected function emitOnPing(Connection $connection)
    {
        $this->eventHandler->trigger(static::PING_EVENT, $this, $connection);
    }

    protected function emitOnPong(Connection $connection)
    {
        $this->eventHandler->trigger(static::PONG_EVENT, $this, $connection);
    }
}