<?php
namespace Bobby\Servers\Exceptions;

class SocketEofException extends \ErrorException
{
    public function __construct($message = "", $code = 0, $filename = __FILE__, $lineno = __LINE__, $previous = null)
    {
        parent::__construct($message, $code, 1, $filename, $lineno, $previous);
    }
}