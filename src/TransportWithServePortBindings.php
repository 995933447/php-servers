<?php
namespace Bobby\Network;

final class TransportWithServePortBindings
{
    protected static $bindings = [

    ];

    public static function transfer(string $transport): string
    {
        return static::$bindings[$transport];
    }

    public static function registerServePort(string $transport, string $servePortClassName)
    {
          static::$bindings[$transport] = $servePortClassName;
    }
}