<?php
namespace Bobby\Servers\Contracts;

interface ConnectionPoolContract extends \Iterator, \Countable
{
    public function add(ConnectionContract $connection);

    public function remove(ConnectionContract $connection);

    public function get($stream): ?ConnectionContract;

    public function exist($stream): bool;
}