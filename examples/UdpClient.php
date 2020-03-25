<?php
$host = '127.0.0.1';
$port = '80';
$message = 'Hello UDP Server';

function send_udp_message($host, $port, $message)
{
    $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    @socket_connect($socket, $host, $port);

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

send_udp_message($host, $port, $message);

