<?php
require __DIR__ . "/../vendor/autoload.php";

$loop = \Bobby\StreamEventLoop\LoopFactory::make();
$serveSocket = new \Bobby\Servers\Socket('0.0.0.0:80');
$config = new \Bobby\Servers\ServerConfig();

$udp = new \Bobby\Servers\Udp\Server($serveSocket, $config, $loop);

$udp->on(\Bobby\Servers\Udp\Server::RECEIVE_EVENT, function (\Bobby\Servers\Udp\Server $server, $address, string $message) {
    echo "Receive socket address:$address and data:$message", PHP_EOL;
    $written = $server->sendTo($address, 'ok!');
    echo "Send buffer num $written", PHP_EOL;
});

$udp->on(\Bobby\Servers\Udp\Server::ERROR_EVENT, function (\Bobby\Servers\Udp\Server $server, Throwable $exception) {
    echo $exception->getTraceAsString();
});

$udp->listen();
$loop->poll();