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

UNIX SERVER:
```php
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
```
#### (以下仅说明常用api,其他api可以通过读源码获得)
# 基础公共类
### Bobby\Servers\ServerConfig 服务器基础配置
method:\
public function setProtocolOptions(array $protocolOptions)\
用于配置如何解析从网络上收到的原始消息，比如处理粘包拆包，合包，解析websocket数据帧格式等。\
参数：\
$protocolOptions array 消息解析协议配置,不同类型server配置内容不同。

public function setServeOptions(array $serveOptions)
参数:\
$serveOptions array 用于server运行行为状态配置,不同类型server配置内容不同。
 
### Bobby\Servers\Socket 用于表示运行服务器的socket对象。
method:\
final public function __construct(string $address, array $context = [])\
参数:\
$address string 合法的服务器地址，如:"0.0.0.0:80"或者unix地址"/tmp/php.sock"。\
$context array socket上下文参数,同stream_context_create参数一样。

public function isOpenedSsl(): bool\
检测是否设置了ssl相关上下文。

public function getAddress(): string\
获取socket地址,同构造函数传入的地址

public function getContext()\
获取socket上下文。

### Bobby\Servers\Connection 用于表示客户端和服务器维持的连接。
method:\
public function isOpenedSsl(): bool\
判断连接是否开启ssl加密。

public function exportStream()\
获取和连接关联的socket stream。

public function isPaused(): bool\
判断连接是否停止接收数据。

public function pause()\
停止读写客户端数据。

public function resume()\
回复读写客户端发送来的数据。

public function getRemoteAddress(): string\
获取客户端地址。  
  
public function isClosed(): bool\
判断客户端是否已关闭。
  
public function getLastReceiveTime(): ?int\
获取上次读取连接数据的时间戳。null代表没有接收过数据。

### Bobby\Servers\ConnectionPool
客户端和server的维持连接的Connection对象连接池送代器，可用于送代正在维持连接的Connection对象。

# TCP SERVE:
### Bobby\Servers\Tcp\Server TCP服务器对象
method:\
public function __construct(Bobby\Servers\Contracts\SocketContract $serveSocket, Bobby\Servers\Contracts\ServerConfigContract $config, Bobby\StreamEventLoop\LoopContract $eventLoop)\
参数:\
$serverSocket Bobby\Servers\Contracts\SocketContract 传入Bobby\Servers\Socket对象。\
$config Bobby\Servers\Contracts\ServerConfigContract 传入Bobby\Servers\ServerConfig对象。可配置项：
```
public function setProtocolOptions(array $protocolOptions)
参数可传入配置同Bobby\ServerNetworkProtocol\Tcp\Parser::__construct(array $decodeOptions = [])参数。详见 https://packagist.org/packages/bobby/server-network-protocol

public function setServeOptions(array $serveOptions)
可用配置项:
max_connection int 接收的最大连接数。
receive_buffer_size int 每个连接能够缓冲的最大单个包长字节数。如果发来的数据包解析得到的不完整包包长大于该参数，则会视为非法数据，会触发on error事件回调并且传入Bobby\Servers\Exceptions\ReceiveBufferFullException实例。
如:
$config->setServeOptions(
    [
        'max_connection' => 1000,
        'receive_buff_size' => 1024,
    ]
)
```
$eventLoop Bobby\StreamEventLoop\LoopContract  实现了Bobby\StreamEventLoop\LoopContract接口的事件循环对象，可以使用\Bobby\StreamEventLoop\LoopFactory::make()获得。(注意:所有server都需要依赖该对象运行)详见：https://packagist.org/packages/bobby/stream-event-loop

public function getConnections(): Bobby\Servers\Contracts\ConnectionPoolContract\
获取Bobby\Servers\Connection对象池送代器。可用于送代表示和服务器正在保持连接的客户端的Connection对象。
  
public function on(string $event, callable $listener)\
设置回调。可设置回调包括:
```php
use Bobby\Servers\Tcp\Server;
use Bobby\Servers\Contracts\ConnectionContract;

// 当有新连接时间发生时候触发
$tcp->on(Server::CONNECT_EVENT, function (Server $server, ConnectionContract $connection) {
    echo 'Socket ' . (int)$connection->exportStream() . ' connected.', PHP_EOL;
});

// 当有消息时候触发
$tcp->on(Server::RECEIVE_EVENT, function (Server $server, ConnectionContract $connection, $data) {
    echo "Receive message:$data", PHP_EOL;
    $server->send($connection, "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nHi");
    $server->close($connection);
});

// 当有连接关闭时触发
$tcp->on(Server::CLOSE_EVENT, function (Server $server, ConnectionContract $connection) {
    echo 'Socket ' . (int)$connection->exportStream() . ' is closed.', PHP_EOL;
});

// 当server进程有用户程序意外错误发生时候回调
$tcp->on(Server::ERROR_EVENT, function (Server $server, ConnectionContract $connection, Throwable $exception) {
    echo $exception->getTraceAsString(), PHP_EOL;
    die;
});

// 当连接满的时候触发
$tcp->on(Server::CONNECT_FULL_EVENT, function (Server $server, ConnectionContract $connection) {
    $server->send($connection, "connection full.");
});
```
    
public function pause()\
服务器停止接收连接和读取客户端数据。
   

public function resume()\
服务器回复接收连接和读取数据。  

public function close(Bobby\Servers\Contracts\ConnectionContract $connection, bool $force = false)\
关闭客户端连接。\
参数:\
$connection Bobby\Servers\Contracts\ConnectionContract 连接对象。\
$force bool 表示是否强制关闭连接。如果否则会在连接所有数据发送完毕后关闭连接，否则表示立即关闭连接。

public function send(Bobby\Servers\Contracts\ConnectionContract $connection, string $message): bool\
给连接的客户端发送数据。\
参数:\
$connection Bobby\Servers\Contracts\ConnectionContract 需要发送的连接对象。\
$message string 消息。

public function listen()\
server开始监听连接。

public function getEventLoop(): Bobby\StreamEventLoop\LoopContract\
获取通过构造函数传入的事件循环对象Bobby\StreamEventLoop\LoopContract接口实现类,详见功能见:https://packagist.org/packages/bobby/stream-event-loop

public function getServeSocket(): Bobby\Servers\Contracts\SocketContract\
获取Bobby\Servers\Socket对象实例。

示例:
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

// 监听服务
$tcp->listen();

// 运行evntloop 服务随之运行
$loop->poll();
```

# Unix Server方法同Tcp Server

# Http Server
### Bobby\Servers\Http\Server HTTP服务器对象。继承自Bobby\Servers\Tcp\Server,拥有从Bobby\Servers\Tcp\Server继承的方法。
和tcp server不同点:\
构造函数参数Bobby\Servers\ServerConfig对象可设置配置项:
```
public function setProtocolOptions(array $protocolOptions)
参数可传入配置同Bobby\ServerNetworkProtocol\Http\Parser::__construct(array $decodeOptions = [])参数。详见 https://packagist.org/packages/bobby/server-network-protocol

public function setServeOptions(array $serveOptions)
可用配置项:
max_connection int 接收的最大连接数。
receive_buffer_size int 每个连接能够缓冲的最大单个包长字节数。如果发来的数据包解析得到的不完整包包长大于该参数，则会视为非法数据，会触发on error事件回调并且传入Bobby\Servers\Exceptions\ReceiveBufferFullException实例。
keep_alive_timeout int 当http客户端发生connection keep alive header时保持的连接时长秒数。不设置则server默认保持60s。
如:
$config->setServeOptions(
    [
        'max_connection' => 1000,
        'keep_alive_timeout' => 65,
    ]
)
```
可设置回调:
```php
// 有请求到达时候回调
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

// server关闭时候回调
$http->on(\Bobby\Servers\Http\Server::CLOSE_EVENT, function (\Bobby\Servers\Http\Server $server, \Bobby\Servers\Connection $connection) {
    echo 'Socket ' . (int)$connection->exportStream() . ' is closed.', PHP_EOL;
});

// 当server进程有用户程序意外错误发生时候回调
$http->on(\Bobby\Servers\Http\Server::ERROR_EVENT, function (\Bobby\Servers\Http\Server $server, \Bobby\Servers\Connection $connection, Throwable $exception) {
    echo $exception->getTraceAsString(), PHP_EOL;
    die;
});
```
on request回调事件用到的对象:\
Bobby\ServerNetworkProtocol\Http\Request 详见https://packagist.org/packages/bobby/server-network-protocol

Bobby\Servers\Http\Response 响应对象。
method:\
public function header(string $header, string $value, bool $ucwords = true)\
设置 HTTP 响应的 Header 信息\
参数:\
header string 设置必须在 end 方法之前 -$key 必须完全符合 Http 的约定，每个单词首字母大写，不得包含中文，下划线或者其他特殊字符。\
$value string 必须填写。\
$ucwords bool 设为 true，底层会自动对 $key 进行约定格式化。

public function status(int $statusCode = ResponseCodeEnum::HTTP_OK, $reason = '')\
发送 Http 状态码。如果只传入了第一个参数 $statusCode 必须为合法的 HttpCode，如 200、502、301、404 等，否则会设置为 200 状态码
如果设置了第二个参数 $reason，$statusCode 可以为任意的数值，包括未定义的 HttpCode，如 499。必须在 $response->end() 之前执行 status 方法

public function gzip(int $level = -1)\
压缩等级，等级越高压缩后的尺寸越小，但 CPU 消耗更多。\
参数:\
$level int 压缩等级。-1代表采用默认等级压缩。  

public function cookie(string $key, string $value = '', int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = false, $isRaw = false)\
设置 HTTP 响应的 cookie 信息。此方法参数与 PHP 的 setcookie 完全一致。会自动会对 $value 进行 urlencode 编码，可使用$isRaw关闭对 $value 的编码处理   

public function end(string $content = '')\
发送 Http 响应体，并结束请求处理。

public function chunk(string $content)\
启用 Http Chunk 分段向浏览器发送相应内容。

public function redirect(string $url, $statusCode = 302)\
发送 Http 跳转。调用此方法会自动 end 发送并结束响应。

public function make(): Bobby\Servers\Http\Response\
构造新的Response 对象。

## Websocket server
### Bobby\Servers\Websocket\Server Websocket服务器对象。继承自Bobby\Servers\Http\Server,拥有从Bobby\Servers\Http\Server继承的方法。
和http server不同点:\
新增方法：
public function getPusher(): Bobby\Servers\Websocket\Pusher\
获取websocket消息推送器。Bobby\Servers\Websocket\Pusher提供以下方法:\
public function ping(Connection $connection)\
发生ping包

public function pong(Connection $connection)\
发生pong包

public function pushString(Connection $connection, string $message)\
推送字符串消息

public function pushFile(Connection $connection, string $message)\
推送文件内容。$message为文件内容。


public function notifyClose(Connection $connection)\
通知客户端server准备关闭连接。

public function getShookConnections(): Bobby\Servers\ConnectionPool\
和getConnections方法类似，不同的是获取已经进行websocket握手升级的Connection对象池送代器

构造函数参数Bobby\Servers\ServerConfig对象可设置配置项:
```
public function setProtocolOptions(array $protocolOptions)
参数可传入配置同Bobby\ServerNetworkProtocol\Websocket\Parser::__construct(array $decodeOptions = [])参数。详见 https://packagist.org/packages/bobby/server-network-protocol

public function setServeOptions(array $serveOptions)
可用配置项:
max_connection int 接收的最大连接数。
receive_buffer_size int 每个连接能够缓冲的最大单个包长字节数。如果发来的数据包解析得到的不完整包包长大于该参数，则会视为非法数据，会触发on error事件回调并且传入Bobby\Servers\Exceptions\ReceiveBufferFullException实例。
keep_alive_timeout int 当http客户端发生connection keep alive header时保持的连接时长秒数。不设置则server默认保持60s。
如:
$config->setServeOptions(
    [
        'max_connection' => 1000,
        'keep_alive_timeout' => 65,
    ]
)
```
可设置回调:
除了包括所有http server可设置回调，还包括以下新增回调:
```
// 当和websocket客户端握手后回调
$websocket->on(\Bobby\Servers\Websocket\Server::OPEN_EVENT, function (
    \Bobby\Servers\Websocket\Server $server,
    \Bobby\Servers\Connection $connection,
    \Bobby\ServerNetworkProtocol\Http\Request $request
) {
    echo "Socket:" . $connection->exportStream() . " opened.\n";
});

// 当接收到websocket客户端消息时候回调
$websocket->on(\Bobby\Servers\Websocket\Server::MESSAGE_EVENT, function (
    \Bobby\Servers\Websocket\Server $server,
    \Bobby\Servers\Connection $connection,
    \Bobby\ServerNetworkProtocol\Websocket\Frame $frame
) {
    foreach ($server->getShookConnections() as $connection) {
        // $frame->payloadData获取websocket客户端发送来的消息
        $data = json_decode($frame->payloadData);
        $data->time = date('Y-m-d H:i:s');
        $data = json_encode($data);
        $server->getPusher()->pushString($connection, $data);
    }
});

// 当接收到websocket客户端ping包格式数据帧时候回调
$websocket->on(\Bobby\Servers\Websocket\Server::PING_EVENT, function (
    \Bobby\Servers\Websocket\Server $server,
    \Bobby\Servers\Connection $connection,
) {
    $serer->getPusher()->pong($connection);
});
```
## UDP SERVER:
### Bobby\Servers\Udp\Server UDP服务器对象
method:\
public function __construct(Bobby\Servers\Contracts\SocketContract $serveSocket, Bobby\Servers\Contracts\ServerConfigContract $config, Bobby\StreamEventLoop\LoopContract $eventLoop)\
参数:\
$serverSocket Bobby\Servers\Contracts\SocketContract 传入Bobby\Servers\Socket对象。\
$config Bobby\Servers\Contracts\ServerConfigContract 传入Bobby\Servers\ServerConfig对象。可配置项：
```
public function setProtocolOptions(array $protocolOptions)
无可设置项

public function setServeOptions(array $serveOptions)
无可设置项目
```
$eventLoop Bobby\StreamEventLoop\LoopContract  实现了Bobby\StreamEventLoop\LoopContract接口的事件循环对象，可以使用\Bobby\StreamEventLoop\LoopFactory::make()获得。(注意:所有server都需要依赖该对象运行)详见：https://packagist.org/packages/bobby/stream-event-loop。
  
public function on(string $event, callable $listener)\
设置回调。可设置回调包括:
```php
// 当消息到达时触发回调
$udp->on(\Bobby\Servers\Udp\Server::RECEIVE_EVENT, function (\Bobby\Servers\Udp\Server $server, $address, string $message) {
    echo "Receive socket address:$address and data:$message", PHP_EOL;
    $written = $server->sendTo($address, 'ok!');
    echo "Send buffer num $written", PHP_EOL;
});

// 当进程发生用户程序意料外的错误时候触发回调
$udp->on(\Bobby\Servers\Udp\Server::ERROR_EVENT, function (\Bobby\Servers\Udp\Server $server, Throwable $exception) {
    echo $exception->getTraceAsString();
});
```
    
public function pause()\
服务器停止接收连接和读取客户端数据。
   

public function resume()\
服务器回复接收连接和读取数据。  

public function sendTo(string $address, string $message): bool\
给连接的客户端发送数据。\
参数:\
$address string 发送地址。\
$message string 消息。

public function listen()\
server开始监听连接。

public function getEventLoop(): Bobby\StreamEventLoop\LoopContract\
获取通过构造函数传入的事件循环对象Bobby\StreamEventLoop\LoopContract接口实现类,详见功能见:https://packagist.org/packages/bobby/stream-event-loop

public function getServeSocket(): Bobby\Servers\Contracts\SocketContract\
获取Bobby\Servers\Socket对象实例。

示例:
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