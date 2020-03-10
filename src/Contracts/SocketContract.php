<?php
namespace Bobby\Servers\Contracts;

interface SocketContract
{
    public function isOpenedSsl();

    public function getAddress(): string;

    public function getContext();
}