<?php
namespace Bobby\Servers\Contracts;

interface EventHandlerContract
{
    public function register(string $event, callable $listener);

    public function trigger(string $event, ...$args);

    public function exist(string $event): bool;
}