<?php
require __DIR__ . '/../vendor/autoload.php';

$loop = \Bobby\StreamEventLoop\LoopFactory::make();
$serveSocket = new \Bobby\Servers\Socket('0.0.0.0:9501');
$config = new \Bobby\Servers\ServerConfig();
$http = new \Bobby\Servers\Http\Server($serveSocket, $config, $loop);

$http->on(\Bobby\Servers\Http\Server::REQUEST_EVENT, function (
    \Bobby\Servers\Http\Server $server,
    \Bobby\ServerNetworkProtocol\Http\Request $request,
    \Bobby\Servers\Http\Response $response
) {
    $request->compressToEnv();
    var_dump($_POST, $_GET, $_FILES, $_SERVER, $_FILES);

    // 模拟异步请求远程api
    $remote = fsockopen($host = 'www.baidu.com', 80);

    $post = "GET / HTTP/1.1\r\n";
    $post .= "Host: $host\r\n";
    $post .= "Connection: close\r\n\r\n";

    $server->getEventLoop()->addLoopStream(
        \Bobby\StreamEventLoop\LoopContract::WRITE_EVENT,
        $remote,
        function ($remote, \Bobby\StreamEventLoop\LoopContract $loop) use ($host, &$post) {
            if (($written = fwrite($remote, $post)) === strlen($post)) {
                sleep(2);
                $loop->removeLoopStream(\Bobby\StreamEventLoop\LoopContract::WRITE_EVENT, $remote);
                $loop->addLoopStream(\Bobby\StreamEventLoop\LoopContract::READ_EVENT, $remote, function ($remote, \Bobby\StreamEventLoop\LoopContract $loop) {
                    $data = fread($remote, 1024);
                    echo $data;
                    $loop->removeLoopStream(\Bobby\StreamEventLoop\LoopContract::READ_EVENT, $remote);
                });
            } else {
                $post = substr($post, 0, $written);
            }

        });

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