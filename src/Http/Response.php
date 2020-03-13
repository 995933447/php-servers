<?php
namespace Bobby\Servers\Http;

use Bobby\Servers\Connection;
use Bobby\Servers\Exceptions\InvalidArgumentException;

class Response
{
    protected $server;

    protected $statusCode;

    protected $reason;

    protected $isGzip = false;

    protected $gzipLevel = -1;

    protected static $reasons = [
        //Informational 1xx
        ResponseCodeEnum::HTTP_CONTINUE => 'Continue',
        ResponseCodeEnum::HTTP_SWITCHING_PROTOCOLS => 'Switching Protocols',
        ResponseCodeEnum::HTTP_PROCESSING => 'Processing',
        //Successful 2xx
        ResponseCodeEnum::HTTP_OK => 'OK',
        ResponseCodeEnum::HTTP_CREATED => 'Created',
        ResponseCodeEnum::HTTP_ACCEPTED => 'Accepted',
        ResponseCodeEnum::HTTP_NONAUTHORITATIVE_INFORMATION => 'Non-Authoritative Information',
        ResponseCodeEnum::HTTP_NO_CONTENT => 'No Content',
        ResponseCodeEnum::HTTP_RESET_CONTENT => 'Reset Content',
        ResponseCodeEnum::HTTP_PARTIAL_CONTENT => 'Partial Content',
        ResponseCodeEnum::HTTP_MULTI_STATUS => 'Multi-Status',
        ResponseCodeEnum::HTTP_ALREADY_REPORTED => 'Already Reported',
        ResponseCodeEnum::HTTP_IM_USED => 'IM Used',
        //Redirection 3xx
        ResponseCodeEnum::HTTP_MULTIPLE_CHOICES => 'Multiple Choices',
        ResponseCodeEnum::HTTP_MOVED_PERMANENTLY => 'Moved Permanently',
        ResponseCodeEnum::HTTP_FOUND => 'Found',
        ResponseCodeEnum::HTTP_SEE_OTHER => 'See Other',
        ResponseCodeEnum::HTTP_NOT_MODIFIED => 'Not Modified',
        ResponseCodeEnum::HTTP_USE_PROXY => 'Use Proxy',
        ResponseCodeEnum::HTTP_UNUSED => '(Unused)',
        ResponseCodeEnum::HTTP_TEMPORARY_REDIRECT => 'Temporary Redirect',
        ResponseCodeEnum::HTTP_PERMANENT_REDIRECT => 'Permanent Redirect',
        //Client Error 4xx
        ResponseCodeEnum::HTTP_BAD_REQUEST => 'Bad Request',
        ResponseCodeEnum::HTTP_UNAUTHORIZED => 'Unauthorized',
        ResponseCodeEnum::HTTP_PAYMENT_REQUIRED => 'Payment Required',
        ResponseCodeEnum::HTTP_FORBIDDEN => 'Forbidden',
        ResponseCodeEnum::HTTP_NOT_FOUND => 'Not Found',
        ResponseCodeEnum::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed',
        ResponseCodeEnum::HTTP_NOT_ACCEPTABLE => 'Not Acceptable',
        ResponseCodeEnum::HTTP_PROXY_AUTHENTICATION_REQUIRED => 'Proxy Authentication Required',
        ResponseCodeEnum::HTTP_REQUEST_TIMEOUT => 'Request Timeout',
        ResponseCodeEnum::HTTP_CONFLICT => 'Conflict',
        ResponseCodeEnum::HTTP_GONE => 'Gone',
        ResponseCodeEnum::HTTP_LENGTH_REQUIRED => 'Length Required',
        ResponseCodeEnum::HTTP_PRECONDITION_FAILED => 'Precondition Failed',
        ResponseCodeEnum::HTTP_REQUEST_ENTITY_TOO_LARGE => 'Request Entity Too Large',
        ResponseCodeEnum::HTTP_REQUEST_URI_TOO_LONG => 'Request-URI Too Long',
        ResponseCodeEnum::HTTP_UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
        ResponseCodeEnum::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE => 'Requested Range Not Satisfiable',
        ResponseCodeEnum::HTTP_EXPECTATION_FAILED => 'Expectation Failed',
        ResponseCodeEnum::HTTP_IM_A_TEAPOT => 'I\'m a teapot',
        ResponseCodeEnum::HTTP_MISDIRECTED_REQUEST => 'Misdirected Request',
        ResponseCodeEnum::HTTP_UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
        ResponseCodeEnum::HTTP_LOCKED => 'Locked',
        ResponseCodeEnum::HTTP_FAILED_DEPENDENCY => 'Failed Dependency',
        ResponseCodeEnum::HTTP_UPGRADE_REQUIRED => 'Upgrade Required',
        ResponseCodeEnum::HTTP_PRECONDITION_REQUIRED => 'Precondition Required',
        ResponseCodeEnum::HTTP_TOO_MANY_REQUESTS => 'Too Many Requests',
        ResponseCodeEnum::HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE => 'Request Header Fields Too Large',
        ResponseCodeEnum::HTTP_CONNECTION_CLOSED_WITHOUT_RESPONSE => 'Connection Closed Without Response',
        ResponseCodeEnum::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS => 'Unavailable For Legal Reasons',
        ResponseCodeEnum::HTTP_CLIENT_CLOSED_REQUEST => 'Client Closed Request',
        //Server Error 5xx
        ResponseCodeEnum::HTTP_INTERNAL_SERVER_ERROR => 'Internal Server Error',
        ResponseCodeEnum::HTTP_NOT_IMPLEMENTED => 'Not Implemented',
        ResponseCodeEnum::HTTP_BAD_GATEWAY => 'Bad Gateway',
        ResponseCodeEnum::HTTP_SERVICE_UNAVAILABLE => 'Service Unavailable',
        ResponseCodeEnum::HTTP_GATEWAY_TIMEOUT => 'Gateway Timeout',
        ResponseCodeEnum::HTTP_VERSION_NOT_SUPPORTED => 'HTTP Version Not Supported',
        ResponseCodeEnum::HTTP_VARIANT_ALSO_NEGOTIATES => 'Variant Also Negotiates',
        ResponseCodeEnum::HTTP_INSUFFICIENT_STORAGE => 'Insufficient Storage',
        ResponseCodeEnum::HTTP_LOOP_DETECTED => 'Loop Detected',
        ResponseCodeEnum::HTTP_NOT_EXTENDED => 'Not Extended',
        ResponseCodeEnum::HTTP_NETWORK_AUTHENTICATION_REQUIRED => 'Network Authentication Required',
        ResponseCodeEnum::HTTP_NETWORK_CONNECTION_TIMEOUT_ERROR => 'Network Connect Timeout Error',
    ];

    protected $headers = [];

    protected $cookie = [];

    protected $isEnded = false;

    protected $isSending = false;

    protected $connection;

    protected $isChunk = false;

    public function __construct(Server $server, Connection $connection)
    {
        $this->server = $server;
        $this->connection = $connection;
    }

    public function header(string $header, string $value, bool $ucwords = true)
    {
        if ($ucwords) {
            $headerExtracts = explode('-', $header);
            foreach ($headerExtracts as &$headerExtract) {
                $headerExtract = ucfirst($headerExtract);
            }
            $header = implode('-', $headerExtracts);
        }

        $this->headers[$header] = $value;

        return $this;
    }

    public function status(int $statusCode = ResponseCodeEnum::HTTP_OK, $reason = '')
    {
        if (!is_numeric($statusCode) || ($statusCode < ResponseCodeEnum::HTTP_CONTINUE || $statusCode > ResponseCodeEnum::HTTP_NETWORK_CONNECTION_TIMEOUT_ERROR)) {
            InvalidArgumentException::defaultThrow('Invalid HTTP status code.');
        }

        if (empty($reason)) {
            $reason = static::$reasons[$statusCode];
        }

        $this->statusCode = $statusCode;
        $this->reason = $reason;

        return $this;
    }

    public function gzip(int $level = -1)
    {
        $this->isGzip = true;
        $this->gzipLevel = $level;

        return $this;
    }

    public function cookie(string $key, string $value = '', int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = false, $isRaw = false)
    {
        if ($secure) {
            if (!$this->server->getServeSocket()->isOpenedSsl()) {
                return;
            }
        }

        if (!$isRaw) {
            $value = rawurldecode($value);
        }

        $cookie = "$key=$value; ";
        if (!empty($expire)) {
            $cookie .= "Max-Age=$expire; ";
        }
        if (!empty($path)) {
            $cookie .= "Path=$path; ";
        }
        if (!empty($domain)) {
            $cookie .= "Domain=$domain; ";
        }
        if ($secure) {
            $cookie .= "Secure; ";
        }
        if ($httpOnly) {
            $cookie .= "HttpOnly; ";
        }

        $this->cookie[$key] = $cookie;

        return $this;
    }

    public function end(string $content = '')
    {
        if ($this->isGzip) {
            $content = gzencode($content, $this->gzipLevel);
        }

        if (!$this->isSending) {
            $this->header('Content-Length', strlen($content));
        }

        if ($this->isChunk) {
            $this->send("0\r\n\r\n");
        } else {
            $this->send($content);
        }

        $this->isEnded = true;
    }

    public function chunk(string $content)
    {
        if ($this->isGzip) {
            $content = gzencode($content, $this->gzipLevel);
        }

        if (!$this->isSending) {
            $this->header('Transfer-Encoding', 'chunked');
            $this->isChunk = true;
        }

        $this->send(dechex(strlen($content)) . "\r\n$content\r\n");

        return $this;
    }

    protected function getHeader(): string
    {
        if (is_null($this->reason) && is_null($this->statusCode)) {
            $this->status();
        }

        if ($this->isGzip) {
            $this->header('Content-Encoding', 'gzip');
        }

        $headerLine = "HTTP/1.1 $this->statusCode $this->reason\r\n";

        if (!empty($this->cookie)) {
            $headerLine .= "Set-Cookie: " . implode(";", $this->cookie) . "\r\n";
        }

        foreach ($this->headers as $header => $value) {
            $headerLine .= "$header: $value\r\n";
        }

        return "$headerLine\r\n";
    }

    protected function send(string $data)
    {
        if ($this->isEnded) {
            throw new \RuntimeException("Response was end.");
        }

        if (!$this->isSending) {
            $this->isSending = true;
            $data = $this->getHeader() . $data;
        }

        $this->server->send($this->connection, $data);
    }

    public function redirect(string $url, $statusCode = 302)
    {
        if ($this->isSending) {
            throw new \RuntimeException("Response was finished.");
        }

        $this->statusCode = $statusCode;
        $this->headers['Location'] = $url;

        $this->end();
    }

    public function make(): Response
    {
        return new static($this->server, $this->connection);
    }

    public function __destruct()
    {
        if ($this->isSending && !$this->isEnded) {
            $this->end();
        }
    }
}