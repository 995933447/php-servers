<?php
$host = '/var/run/myserv.sock';
$message = 'Hello UDP Server';

function send_unix_message($host, $message)
{
    $socket = socket_create(AF_UNIX, SOCK_STREAM, getprotobyname('unix'));
    @socket_connect($socket, $host);

    $num = 0;
    $length = strlen($message);
    do
    {
        $buffer = substr($message, $num);
        $ret = @socket_write($socket, $buffer);
        $num += $ret;
    } while ($num < $length);

    @socket_recvfrom($socket, $buffer, 1024, 0, $address, $service_port);

    var_dump($buffer);

    socket_close($socket);

    // UDP 是一种无链接的传输层协议, 不需要也无法获取返回消息
    return true;
}

send_unix_message($host, $message);

