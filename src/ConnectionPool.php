<?php
namespace Bobby\Network;

use Bobby\Network\Contracts\ConnectionPoolContract;
use Bobby\Network\Contracts\ConnectionContract;

class ConnectionPool implements ConnectionPoolContract
{
    protected $pool = [];

    protected $position = 0;

    /**
     * @inheritDoc
     */
    public function current()
    {
        return current($this->pool);
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        $this->position++;
        next($this->pool);
    }

    /**
     * @inheritDoc
     */
    public function key()
    {
        return key($this->pool);
    }

    /**
     * @inheritDoc
     */
    public function valid()
    {
        return $this->position < $this->count();
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        $this->position = 0;
        reset($this->pool);
    }

    /**
     * @inheritDoc
     */
    public function count()
    {
        return count($this->pool);
    }

    public function add(ConnectionContract $connection)
    {
        $this->pool[(int)$connection->exportStream()] = $connection;
    }

    public function remove(ConnectionContract $connection)
    {
        if (isset($this->pool[$streamId = (int)$connection->exportStream()])) {
            unset($this->pool[$streamId]);
        }
    }

    public function get($stream): ?ConnectionContract
    {
        return $this->pool[(int)$stream]?? null;
    }

    public function exist($stream): bool
    {
        return isset($this->pool[(int)$stream]);
    }
}