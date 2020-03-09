<?php
require __DIR__ . "/../vendor/autoload.php";

use Bobby\StreamEventLoop\LoopFactory;
use Bobby\Network\Socket;
use Bobby\Network\ServerConfig;
use Bobby\Network\Servers\TcpServer;
use Bobby\Network\Contracts\ConnectionContract;

$loop = LoopFactory::make();
$serveSocket = new Socket("0.0.0.0:80");
$config = new ServerConfig();

$tcp = new TcpServer($serveSocket, $config, $loop);

$tcp->on(TcpServer::CONNECT_EVENT, function (TcpServer $server, ConnectionContract $connection) {
    echo 'Socket ' . (int)$connection->exportStream() . ' connected.', PHP_EOL;
});

$tcp->on(TcpServer::RECEIVE_EVENT, function (TcpServer $server, ConnectionContract $connection, $data) {
    echo "Receive message:$data", PHP_EOL;
    $server->send($connection->exportStream(), "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nHi");
    $server->close($connection);
});

$tcp->on(TcpServer::CLOSE_EVENT, function (TcpServer $server, ConnectionContract $connection) {
    echo 'Socket ' . (int)$connection->exportStream() . ' is closed.', PHP_EOL;
});

$tcp->on(TcpServer::ERROR_EVENT, function (TcpServer $server, ConnectionContract $connection, Throwable $exception) {
    echo $exception->getTraceAsString(), PHP_EOL;
});

$tcp->listen();
$loop->poll();