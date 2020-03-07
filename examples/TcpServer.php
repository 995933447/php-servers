<?php
require __DIR__ . "/../vendor/autoload.php";

$loop = \Bobby\StreamEventLoop\LoopFactory::make();
$serveSocket = new \Bobby\Network\Socket("tcp://0.0.0.0:80");
$config = new \Bobby\Network\ServerConfig();

$tcp = new \Bobby\Network\Servers\TcpServer($serveSocket, $config, $loop);

$tcp->on(\Bobby\Network\Servers\TcpServer::CONNECT_EVENT, function (\Bobby\Network\Servers\TcpServer $server, \Bobby\Network\Connection $connection) {
    echo 'Socket ' . (int)$connection->exportStream() . ' conncted.', PHP_EOL;
});

$tcp->on(\Bobby\Network\Servers\TcpServer::RECEIVE_EVENT, function (\Bobby\Network\Servers\TcpServer $server, \Bobby\Network\Connection $connection, $data) {
    echo "Receive message:$data";
    $server->send($connection->exportStream(), "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nHi");
    $server->close($connection);
});

$tcp->on(\Bobby\Network\Servers\TcpServer::CLOSE_EVENT, function (\Bobby\Network\Servers\TcpServer $server, \Bobby\Network\Connection $connection) {
    echo 'Socket ' . (int)$connection->exportStream() . ' is closed.', PHP_EOL;
});

$tcp->on(\Bobby\Network\Servers\TcpServer::ERROR_EVENT, function (\Bobby\Network\Servers\TcpServer $server, \Bobby\Network\Connection $connection, Exception $exception) {
        var_dump($exception);
});

$tcp->listen();
$loop->poll();