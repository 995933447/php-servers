<?php
namespace Bobby\Network\Exceptions;

class InvalidArgumentException extends \InvalidArgumentException
{
    public static function mustMoreThanZero(int $order)
    {
        return new static("The $order argument can not set less than 0.");
    }

    public static function mustBeNotEmpty($order)
    {
        return new static("The $order argument can not set empty value.");
    }

    public static function defaultThrow(string $message = '', int $code = null)
    {
        if ($message) {
            if (is_null($code)) {
                return new static($message);
            }
            return new static($message, $code);
        }
        return new static();
    }
}