<?php
namespace Bobby\Servers\Http;

use Bobby\Servers\Connection;

class ClearAliveConnectionTimerPool extends \SplObjectStorage
{
    public function save(Connection $connection, int $timerId)
    {
        $this[$connection] = $timerId;
    }

    public function exists(Connection $connection): bool
    {
        return isset($this[$connection]);
    }

    public function get(Connection $connection): int
    {
        return $this[$connection];
    }

    public function remove(Connection $connection)
    {
        if ($this->exists($connection)) {
            unset($this[$connection]);
        }
    }
}