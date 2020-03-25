<?php
require __DIR__ . "/../vendor/autoload.php";

$serveSocket = new \Bobby\Servers\Socket("/var/run/myserv.sock");
$config = new \Bobby\Servers\ServerConfig();
$loop = \Bobby\StreamEventLoop\LoopFactory::make();
$unixDomain = new \Bobby\Servers\Unix\Server($serveSocket, $config, $loop);

$unixDomain->on(
    \Bobby\Servers\Unix\Server::CONNECT_EVENT,
    function (
        \Bobby\Servers\Unix\Server $server,
        \Bobby\Servers\Contracts\ConnectionContract $connection
    ) {
        echo 'Socket ' . (int)$connection->exportStream() . ' connected.', PHP_EOL;
});

$unixDomain->on(
    \Bobby\Servers\Unix\Server::RECEIVE_EVENT,
    function (
        \Bobby\Servers\Unix\Server $server,
        \Bobby\Servers\Contracts\ConnectionContract $connection,
        $data
    ) {
        echo "Receive message:$data", PHP_EOL;
        $server->send($connection, "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nHi");
        $server->close($connection);
});

$unixDomain->on(
    \Bobby\Servers\Unix\Server::CLOSE_EVENT,
    function (
        \Bobby\Servers\Unix\Server $server,
        \Bobby\Servers\Contracts\ConnectionContract $connection
    ) {
        echo 'Socket ' . (int)$connection->exportStream() . ' is closed.', PHP_EOL;
});

$unixDomain->on(
    \Bobby\Servers\Unix\Server::ERROR_EVENT,
    function (
        \Bobby\Servers\Unix\Server $server,
        \Bobby\Servers\Contracts\ConnectionContract $connection,
        Throwable $exception
    ) {
        echo $exception->getTraceAsString(), PHP_EOL;
        die;
});

$unixDomain->listen();
$loop->poll();