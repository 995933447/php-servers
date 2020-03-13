示例:\
TCP SERVER:
```php
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
    $server->send($connection, "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nHi");
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
```
HTTP SERVER:
```php
require __DIR__ . '/../vendor/autoload.php';

$loop = \Bobby\StreamEventLoop\LoopFactory::make();
$serveSocket = new \Bobby\Servers\Socket('0.0.0.0:80');
$config = new \Bobby\Servers\ServerConfig();
$http = new \Bobby\Servers\Http\Server($serveSocket, $config, $loop);

$http->on(\Bobby\Servers\Http\Server::REQUEST_EVENT, function (
    \Bobby\Servers\Http\Server $server,
    \Bobby\ServerNetworkProtocol\Http\Request $request,
    \Bobby\Servers\Http\Response $response
) {
    $request->compressToEnv();
    var_dump($_POST, $_GET, $_FILES, $_SERVER, $_FILES);
    $response
        ->gzip(5)
        ->header('Vary', 'Accept-Encoding')
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->end("how are you34567~~~你好啊是");

//    $response->redirect('http://www.baidu.com/');
//
//    $response
//        ->header('Content-Type', 'text/html; charset=UTF-8')
//        ->header("Extract", 1)
//        ->cookie("PHPSSID", 123)
//        ->chunk("分段传输开始")
//        ->chunk("Hello world!!!!~")
//        ->chunk("Yoyo.")
//        ->chunk("PHP is best language.")
//        ->chunk("最后一块")
//        ->end();
});

$http->listen();
$loop->poll();
```
WEBSOCKET SERVER:
```php
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
```
UDP SERVER:
```php
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
```