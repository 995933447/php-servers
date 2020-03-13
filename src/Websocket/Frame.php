<?php
namespace Bobby\Servers\Websocket;

use Bobby\Servers\Exceptions\InvalidArgumentException;

class Frame
{
    public static function encode($opcode, string $payload): string
    {
        switch ($opcode) {
            case OpcodeEnum::PING:
            case OpcodeEnum::PONG:
            case OpcodeEnum::OUT_CONNECT:
                $buff = pack("H*", sprintf("%x%x", FinWith3RsvEnum::FINISH, $opcode));
                break;
            case OpcodeEnum::BINARY:
            case OpcodeEnum::TEXT:
                if (($payloadLen = strlen($payload)) <= 125) {
                    $buff = pack("H*", sprintf("%x%x", FinWith3RsvEnum::FINISH, $opcode)) . chr($payloadLen) . $payload;
                } else if ($payloadLen <= 65535) {
                    $buff = pack("H*", sprintf("%x%x", FinWith3RsvEnum::FINISH, $opcode)) . chr(126) . pack("n", $payloadLen) . $payload;
                } else {
                    $buff = pack("H*", sprintf("%x%x", FinWith3RsvEnum::FINISH, $opcode)) . chr(127) . pack("J", $payloadLen) . $payload;
                }
                break;
            default:
                throw InvalidArgumentException::defaultThrow("$opcode is invalid.");
        }

        return $buff;
    }
}