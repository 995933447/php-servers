<?php
namespace Bobby\Servers\Utils;

class EventHandler
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
        if (isset($this->listen[$event])) {
            call_user_func_array($this->listen[$event], $args);
        }
    }
}