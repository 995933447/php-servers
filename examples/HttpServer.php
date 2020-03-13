<?php
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