<?php
require __DIR__ . '/../vendor/autoload.php';

$loop = \Bobby\StreamEventLoop\LoopFactory::make();
$serveSocket = new \Bobby\Servers\Socket('0.0.0.0:80');
$config = new \Bobby\Servers\ServerConfig();
$http = new \Bobby\Servers\Http\Server($serveSocket, $config, $loop);

$http->on(\Bobby\Servers\Http\Server::REQUEST_EVENT, function (
    \Bobby\Servers\Http\Server $server,
    \Bobby\Servers\Connection $connection,
    \Bobby\ServerNetworkProtocol\Http\Request $request
) {
    $request->compressToEnv();
    var_dump($_POST, $_GET, $_FILES,$_SERVER, $_FILES);
    $server->send($connection->exportStream(), "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOk");
});


$http->listen();
$loop->poll();