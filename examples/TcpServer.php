<?php
require __DIR__ . "/../vendor/autoload.php";

use Bobby\StreamEventLoop\LoopFactory;
use Bobby\Servers\Socket;
use Bobby\Servers\ServerConfig;
use Bobby\Servers\Tcp\Server;
use Bobby\Servers\Contracts\ConnectionContract;

$loop = LoopFactory::make();
$serveSocket = new Socket("0.0.0.0:80");
$config = new ServerConfig();
$tcp = new Server($serveSocket, $config, $loop);

$tcp->on(Server::CONNECT_EVENT, function (Server $server, ConnectionContract $connection) {
    echo 'Socket ' . (int)$connection->exportStream() . ' connected.', PHP_EOL;
});

$tcp->on(Server::RECEIVE_EVENT, function (Server $server, ConnectionContract $connection, $data) {
    echo "Receive message:$data", PHP_EOL;
    $server->send($connection->exportStream(), "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nHi");
    $server->close($connection);
});

$tcp->on(Server::CLOSE_EVENT, function (Server $server, ConnectionContract $connection) {
    echo 'Socket ' . (int)$connection->exportStream() . ' is closed.', PHP_EOL;
});

$tcp->on(Server::ERROR_EVENT, function (Server $server, ConnectionContract $connection, Throwable $exception) {
    echo $exception->getTraceAsString(), PHP_EOL;
    die;
});

$tcp->listen();
$loop->poll();
