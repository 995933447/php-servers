<?php
namespace Bobby\Network\Contracts;

use Bobby\Network\Utils\MagicGetterTrait;

abstract class ServerConfigContract
{
    use MagicGetterTrait;

    protected $protocolOptions = [];

    protected $serveOptions = [];

    public function setProtocolOptions(array $protocolOptions)
    {
        $this->protocolOptions = $protocolOptions;
    }

    public function setServeOptions(array $serveOptions)
    {
        $this->serveOptions = $serveOptions;
    }
}