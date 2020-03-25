<?php
namespace Bobby\Servers;

use Bobby\Servers\Exceptions\SocketEofException;
use Bobby\Servers\Exceptions\SocketReadFailedException;
use Bobby\Servers\Exceptions\InvalidArgumentException;
use Bobby\ServerNetworkProtocol\ParserContract;
use Bobby\Servers\Contracts\ConnectionContract;
use Bobby\ServerNetworkProtocol\Tcp\Parser;

class Connection implements ConnectionContract
{
    protected $lastReceiveTime;

    protected $stream;

    protected $protocolParser;

    protected $remoteAddress;
    
    protected $isReadyClose = false;

    protected $isPaused = false;

    protected $isOpenedSsl = false;

    public function __construct($stream, string $remoteAddress, ParserContract $protocolParser = null)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw InvalidArgumentException::defaultThrow('First argument must be a stream resource.');
        }

        $this->protocolParser = $protocolParser?: new Parser();
        $this->stream = $stream;
        $this->remoteAddress = $remoteAddress;
    }

    public function setProtocolParser(ParserContract $protocolParser)
    {
        $this->protocolParser = $protocolParser;
    }

    public function getProtocolParser(): ParserContract
    {
        return $this->protocolParser;
    }

    public function openedSsl()
    {
        if (!$this->isOpenedSsl) {
            $this->isOpenedSsl = true;
        }
    }

    public function isOpenedSsl()
    {
        return $this->isOpenedSsl;
    }

    public function exportStream()
    {
        return $this->stream;
    }

    public function isPaused()
    {
        return $this->isPaused;
    }

    public function pause()
    {
        if (!$this->isPaused) {
            $this->isPaused = true;
        }
    }

    public function resume()
    {
        if ($this->isPaused) {
            $this->isPaused = false;
        }
    }

    public function receiveBuffer(): int
    {
        if ($this->isPaused) {
            return 0;
        }

        $readException = null;
        set_error_handler(function ($errno, $error, $file, $line) use ($readException) {
            $readException = new SocketReadFailedException($error, $errno, $file, $line);
        });

        $data = stream_get_contents($this->stream);

        restore_error_handler();

        if ($data === '' && feof($this->stream)) {
            $this->close();
            throw new SocketEofException();
        } if ($data !== false) {
            $this->protocolParser->input($data);
            $this->lastReceiveTime = time();
            return strlen($data);
        } else if ($readException instanceof \Throwable) {
            throw $readException;
        }
    }

    public function decodeReceivedBuffer(): array
    {
        return $this->protocolParser->decode();
    }

    public function getReceivedBuffer(): string
    {
        return $this->protocolParser->getBuffer();
    }

    public function getReceivedBufferLength(): int
    {
        return $this->protocolParser->getBufferLength();
    }

    public function clearReceivedBuffer()
    {
        $this->protocolParser->clearBuffer();
    }

    public function getRemoteAddress(): string
    {
        return $this->remoteAddress;
    }
    
    public function readyClose()
    {
        $this->pause();
        if (!$this->isReadyClose) {
            $this->isReadyClose = true;
        }
    }
    
    public function isReadyClose(): bool
    {
        return $this->isReadyClose;
    }
  
    public function close()
    {
        if (!$this->isClosed()) {
            fclose($this->stream);
            $this->pause();
        }
    }
    
    public function isClosed(): bool
    {
        if (($isClosed = (get_resource_type($this->stream) !== 'stream' || feof($this->stream))) && !$this->isPaused) {
            $this->pause();
        }
        return $isClosed;
    }

    public function getLastReceiveTime(): ?int
    {
        return $this->lastReceiveTime;
    }
}