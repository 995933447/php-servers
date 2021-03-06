<?php
namespace Bobby\Servers\Contracts;

interface ConnectionContract
{
    public function openedSsl();

    public function isOpenedSsl(): bool;

    public function exportStream();

    public function isPaused(): bool;

    public function pause();

    public function resume();

    public function receiveBuffer(): int;

    public function decodeReceivedBuffer(): array;

    public function getReceivedBuffer(): string;

    public function getReceivedBufferLength(): int;

    public function clearReceivedBuffer();

    public function getLastReceiveTime(): ?int;

    public function getRemoteAddress(): string;

    public function readyClose();

    public function isReadyClose(): bool;

    public function close();

    public function isClosed(): bool;
}