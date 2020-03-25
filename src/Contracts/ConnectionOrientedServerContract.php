<?php
namespace Bobby\Servers\Contracts;

use Bobby\Servers\Contracts\ConnectionContract;
use Bobby\Servers\ConnectionPool;
use Bobby\Servers\Contracts\ConnectionPoolContract;
use Bobby\Servers\Contracts\SocketContract;
use Bobby\Servers\SendingBufferPool;
use Bobby\Servers\Contracts\ServerContract;
use Bobby\Servers\Exceptions\SocketEofException;
use Bobby\Servers\EventHandler;
use Bobby\Servers\Exceptions\InvalidArgumentException;
use Bobby\StreamEventLoop\LoopContract;
use Bobby\Servers\Connection;
use Bobby\Servers\Exceptions\SocketWriteFailedException;
use Bobby\Servers\Exceptions\ReceiveBufferFullException;
use Bobby\ServerNetworkProtocol\Tcp\Parser;

abstract class ConnectionOrientedServerContract extends ServerContract
{

    protected $transport;

    const CONNECT_EVENT = 'connect';

    const RECEIVE_EVENT = 'receive';

    const CLOSE_EVENT = 'close';

    const ERROR_EVENT = 'error';

    const CONNECT_FULL_EVENT = 'connect_full';

    protected $connections;

    protected $eventHandler;

    protected $server;

    protected $isPaused = false;

    protected $allowEvents = [self::CONNECT_EVENT, self::RECEIVE_EVENT, self::CLOSE_EVENT, self::ERROR_EVENT, self::CONNECT_EVENT, self::CONNECT_FULL_EVENT];

    protected $sendingBuffers;

    protected $readyCloseConnections;

    public function __construct(SocketContract $serveSocket, ServerConfigContract $config, LoopContract $eventLoop)
    {
        parent::__construct($serveSocket, $config, $eventLoop);

        $this->readyCloseConnections = new ConnectionPool();
        $this->connections = new ConnectionPool();
        $this->eventHandler = new EventHandler();
        $this->sendingBuffers = new SendingBufferPool();
    }

    public function getConnections(): ConnectionPoolContract
    {
        return $this->connections;
    }

    public function on(string $event, callable $listener)
    {
        if (!in_array($event, $this->allowEvents)) {
            throw InvalidArgumentException::defaultThrow("Event $event can not allow set.");
        }

        $this->eventHandler->register($event, $listener);
    }

    public function pause()
    {
        $this->eventLoop->removeLoopStream(LoopContract::READ_EVENT, $this->server);
        $this->isPaused = true;
    }

    public function resume()
    {
        $this->eventLoop->addLoopStream(LoopContract::READ_EVENT, $this->server, function () {
            $this->accept();
        });
    }

    public function close(ConnectionContract $connection, bool $force = false)
    {
        $this->connections->remove($connection);

        $stream = $connection->exportStream();
        $removeLoopEvents = LoopContract::READ_EVENT;

        if (!$force && $this->sendingBuffers->exist($stream)) {
            $connection->readyClose();
            $this->readyCloseConnections->add($connection);
        } else {
            $removeLoopEvents |= LoopContract::WRITE_EVENT;
            $connection->close();
            $this->sendingBuffers->remove($stream);
            $this->emitOnClose($connection);
        }

        $this->eventLoop->removeLoopStream($removeLoopEvents, $stream);
    }

    public function send(ConnectionContract $connection, string $message): bool
    {
        if ($connection->isClosed() || $connection->isPaused()) {
            return false;
        }

        $this->sendingBuffers->add($connection->exportStream(), $message);

        if (!$this->serveSocket->isOpenedSsl() || $connection->isOpenedSsl()) {
            $this->writeTo($connection);
        }

        return true;
    }

    protected function writeTo(ConnectionContract $connection)
    {
        if (!$this->sendingBuffers->exist($stream = $connection->exportStream())) {
            return;
        }

        $message = $this->sendingBuffers->get($stream);

        $writeException = null;
        set_error_handler(function ($errno, $error, $file, $line) use ($writeException) {
            $writeException = new SocketWriteFailedException($error, $errno, $file, $line);
        });

        if ($this->serveSocket->isOpenedSsl() && (PHP_VERSION_ID < 70018 || (PHP_VERSION_ID >= 70100 && PHP_VERSION_ID < 70104))) {
            $written = fwrite($stream, $message, 8192);
        } else {
            $written = fwrite($stream, $message);
        }

        restore_error_handler();

        if (!is_null($writeException)) {
            $this->emitOnError($connection, $writeException);
        } else if ($written < strlen($message)) {
            $this->sendingBuffers->set($stream, substr($message, $written));
            $this->eventLoop->addLoopStream(LoopContract::WRITE_EVENT, $stream, function ($stream) use ($connection) {
                $this->writeTo($connection);
            });
        } else {
            $this->eventLoop->removeLoopStream(LoopContract::WRITE_EVENT, $stream);
            $this->sendingBuffers->remove($stream);

            if ($this->readyCloseConnections->exist($stream)) {
                $this->close($connection);
            }
        }
    }

    protected function sslShakeWith(ConnectionContract $connection): bool
    {
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_SERVER;
        if (PHP_VERSION_ID < 70200 && PHP_VERSION_ID >= 50600) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_0_SERVER | STREAM_CRYPTO_METHOD_TLSv1_1_SERVER | STREAM_CRYPTO_METHOD_TLSv1_2_SERVER;
        }

        $sslShackException = null;
        set_error_handler(function ($errno, $error, $file, $line) use ($sslShackException) {
            $sslShackException = new SocketEofException("Unable to complete TLS handshake:$error", $errno, $file, $line);
        });

        $result = stream_socket_enable_crypto($connection->exportStream(), true, $cryptoMethod);

        restore_error_handler();

        if ($result === false) {
            $this->emitOnError($connection, $sslShackException?: new SocketEofException('Connection lost during TLS handshake'));
            $this->close($connection, true);
            return false;
        }

        $connection->openedSsl();
        if ($this->sendingBuffers->exist($stream = $connection->exportStream())) {
            $this->writeTo($connection);
        }

        return true;
    }

    protected function accept()
    {
        $connectSocketStream = stream_socket_accept($this->server, 0, $remoteAddress);

        stream_set_blocking($connectSocketStream, false);

        $connection = new Connection($connectSocketStream, $remoteAddress, new Parser($this->config->protocolOptions));

        if (isset($this->config->serveOptions['max_connection']) && $this->config->serveOptions['max_connection'] <= $this->connections->count()) {
            $this->emitOnConnectFull($connection);
            $this->close($connection);
            return;
        }

        $this->connections->add($connection);
        $this->emitOnConnect($connection);

        $this->eventLoop->addLoopStream(LoopContract::READ_EVENT, $connectSocketStream, function ($connectSocketStream) {
            $connection = $this->connections->get($connectSocketStream);
            if ($this->serveSocket->isOpenedSsl() && !$connection->isOpenedSsl()) {
                $this->sslShakeWith($connection);
            }
            $this->receive($connection);
        });
    }

    protected function receive(ConnectionContract $connection)
    {
        if (!$this->isPaused) {
            try {
                $connection->receiveBuffer();
            } catch (\Exception $e) {
                if ($e instanceof SocketEofException) {
                    $this->close($connection);
                } else {
                    $this->emitOnError($connection, $e);
                    return;
                }
            }

            try {
                $messages = $connection->decodeReceivedBuffer();
            } catch (\Exception $e) {
                $this->emitOnError($connection, $e);
                $messages = [];
            }

            foreach ($messages as $message) {
                $this->emitOnReceive($connection, $message);
            }

            if (isset($this->config->serveOptions['receive_buffer_size']) && $this->config->serveOptions['receive_buffer_size'] > $connection->getReceivedBufferLength()) {
                $this->emitOnError($connection, new ReceiveBufferFullException());
            }
        }
    }

    protected function emitOnReceive(ConnectionContract $connection, $message)
    {
        $this->eventHandler->trigger(static::RECEIVE_EVENT, $this, $connection, $message);
    }

    protected function emitOnConnect(ConnectionContract $connection)
    {
        $this->eventHandler->trigger(static::CONNECT_EVENT, $this, $connection);
    }

    protected function emitOnClose(ConnectionContract $connection)
    {
        $this->eventHandler->trigger(static::CLOSE_EVENT, $this, $connection);
    }

    protected function emitOnError(ConnectionContract $connection, \Throwable $exception)
    {
        $this->eventHandler->trigger(static::ERROR_EVENT, $this, $connection, $exception);
    }

    protected function emitOnConnectFull(ConnectionContract $connection)
    {
        $this->eventHandler->trigger(static::CONNECT_FULL_EVENT, $this, $connection);
    }
}