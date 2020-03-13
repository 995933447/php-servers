<?php
require __DIR__ . "/../vendor/autoload.php";

$loop = \Bobby\StreamEventLoop\LoopFactory::make();

$serveSocket = new \Bobby\Servers\Socket("0.0.0.0:80");
$config = new \Bobby\Servers\ServerConfig();
$websocket = new Bobby\Servers\Websocket\Server($serveSocket, $config, $loop);

$websocket->on(\Bobby\Servers\Websocket\Server::OPEN_EVENT, function (
    \Bobby\Servers\Websocket\Server $server,
    \Bobby\Servers\Connection $connection,
    \Bobby\ServerNetworkProtocol\Http\Request $request
) {
    echo "Socket:" . $connection->exportStream() . " opened.\n";
});

$websocket->on(\Bobby\Servers\Websocket\Server::MESSAGE_EVENT, function (
    \Bobby\Servers\Websocket\Server $server,
    \Bobby\Servers\Connection $connection,
    \Bobby\ServerNetworkProtocol\Websocket\Frame $frame
) {
    foreach ($server->getShookConnections() as $connection) {
        $data = json_decode($frame->payloadData);
        $data->time = date('Y-m-d H:i:s');
        $data = json_encode($data);
        $server->getPusher()->pushString($connection, $data);
    }
});

$websocket->on(\Bobby\Servers\Websocket\Server::REQUEST_EVENT, function (
    \Bobby\Servers\Websocket\Server $server,
    \Bobby\ServerNetworkProtocol\Http\Request $request,
    \Bobby\Servers\Http\Response $response
) {
    if (isset($request->get['msg'])) {
        foreach ($server->getShookConnections() as $connection) {
            $data = json_encode(['content' => $request->get['msg'], 'username' => 'admin', 'type' => 2, 'time' => date('Y-m-d H:i:s')]);
            $server->getPusher()->pushString($connection, $data);
        }
    }

    $response->end(json_encode(['code' => 1, 'msg' => 'ok!']));
});

$websocket->listen();
$loop->poll();