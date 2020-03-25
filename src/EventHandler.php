<?php
namespace Bobby\Servers;

use Bobby\Servers\Contracts\EventHandlerContract;

class EventHandler implements EventHandlerContract
{
    protected $listen = [];

    public function register(string $event, callable $listener)
    {
        if (!is_null($listener)) {
            $this->listen[$event] = $listener;
        } else if (isset($this->listen[$event])) {
            unset($this->listen[$event]);
        }
    }

    public function trigger(string $event, ...$args)
    {
        if ($this->exist($event)) {
            call_user_func_array($this->listen[$event], $args);
        }
    }

    public function exist(string $event): bool
    {
        return isset($this->listen[$event]);
    }
}