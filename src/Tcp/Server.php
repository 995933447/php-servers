<?php
namespace Bobby\Servers\Tcp;

use Bobby\Servers\Connection;
use Bobby\Servers\Contracts\ConnectionContract;
use Bobby\Servers\ConnectionPool;
use Bobby\Servers\Contracts\ConnectionPoolContract;
use Bobby\Servers\Contracts\SocketContract;
use Bobby\Servers\Exceptions\ReceiveBufferFullException;
use Bobby\Servers\Exceptions\SocketWriteFailedException;
use Bobby\Servers\SendingBufferPool;
use Bobby\Servers\ServerConfig;
use Bobby\Servers\Contracts\ServerContract;
use Bobby\Servers\Exceptions\SocketEofException;
use Bobby\Servers\Utils\EventHandler;
use Bobby\Servers\Exceptions\InvalidArgumentException;
use Bobby\ServerNetworkProtocol\Tcp\Parser;
use Bobby\StreamEventLoop\LoopContract;

class Server extends ServerContract
{
    const CONNECT_EVENT = 'connect';

    const RECEIVE_EVENT = 'receive';

    const CLOSE_EVENT = 'close';

    const ERROR_EVENT = 'error';

    protected $connections;

    protected $eventHandler;

    protected $server;

    protected $isPaused = false;

    protected $allowEvents = [self::CONNECT_EVENT, self::RECEIVE_EVENT, self::CLOSE_EVENT, self::ERROR_EVENT];

    protected $sendingBuffers;

    public function __construct(SocketContract $serveSocket, ServerConfig $config, LoopContract $eventLoop)
    {
        parent::__construct($serveSocket, $config, $eventLoop);

        $this->connections = new ConnectionPool();
        $this->eventHandler = new EventHandler();
        $this->sendingBuffers = new SendingBufferPool();
    }

    public function getConnections(): ?ConnectionPoolContract
    {
        return $this->connections;
    }

    public function on(string $event, callable $listener)
    {
        if (!in_array($event, $this->allowEvents)) {
            throw InvalidArgumentException::defaultThrow("First event can not allow set.");
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
        $removeEventLoopEvents = LoopContract::READ_EVENT;
        $stream = $connection->exportStream();

        if (!$force && $this->sendingBuffers->exist($stream)) {
            $connection->readyClose();
        } else {
            $removeEventLoopEvents |= LoopContract::WRITE_EVENT;
            $connection->close();
            $this->connections->remove($connection);
            $this->sendingBuffers->remove($stream);
            $this->emitOnClose($connection);
        }

        $this->eventLoop->removeLoopStream($removeEventLoopEvents, $stream);
    }

    public function send($stream, string $message): bool
    {
        if (!is_resource($stream) || is_null($connection = $this->connections->get($stream)) || $connection->isClosed() || $connection->isPaused()) {
            return false;
        }

        $this->sendingBuffers->add($stream, $message);

        if (!$this->serveSocket->isOpenedSsl() || $connection->isOpenedSsl()) {
            $this->writeTo($stream);
        }

        return true;
    }

    public function writeTo($stream)
    {
        if (!$this->sendingBuffers->exist($stream)) {
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
            $this->emitOnError($this->connections->get($stream), $writeException);
        } else if ($written < strlen($message)) {
            $this->sendingBuffers->set($stream, substr($message, $written));
            $this->eventLoop->addLoopStream(LoopContract::WRITE_EVENT, $stream, function ($stream) {
                $this->writeTo($stream);
            });
        } else {
            $this->eventLoop->removeLoopStream(LoopContract::WRITE_EVENT, $stream);
            $this->sendingBuffers->remove($stream);

            if (($connection = $this->connections->get($stream))->isReadyClose()) {
                $this->close($connection);
            }
        }
    }

    protected function sslShake(ConnectionContract $connection): bool
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
           $this->writeTo($stream);
        }

        return true;
    }

    protected function accept()
    {
        $connectSocketStream = stream_socket_accept($this->server, 0, $remoteAddress);

        if (isset($this->config->serveOptions['max_connection']) && $this->config->serveOptions['max_connection'] <= $this->connections->count()) {
            fclose($connectSocketStream);
            return;
        }

        stream_set_blocking($connectSocketStream, false);

        $connection = new Connection($connectSocketStream, $remoteAddress, new Parser($this->config->protocolOptions));
        $this->connections->add($connection);
        $this->emitOnConnect($connection);

        $this->eventLoop->addLoopStream(LoopContract::READ_EVENT, $connectSocketStream, function ($connectSocketStream) {
            $connection = $this->connections->get($connectSocketStream);
            if ($this->serveSocket->isOpenedSsl() && !$connection->isOpenedSsl()) {
                $this->sslShake($connection);
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
                $this->emitOnError($connection, $e);
                return;
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

    public function listen()
    {
        stream_context_set_option($this->serveSocket->getContext(), 'socket', 'so_reuseport', 1);

        $this->server = stream_socket_server(
            "tcp://" . $this->serveSocket->getAddress(),
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
}