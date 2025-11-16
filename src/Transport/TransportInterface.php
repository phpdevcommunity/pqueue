<?php

namespace PhpDevCommunity\PQueue\Transport;

use PhpDevCommunity\PQueue\Transport\Message\TransportMessage;

interface TransportInterface
{
    public function send(string $body, \DateTimeInterface $availableAt, bool $retry): void;
    public function getNextAvailableMessages(): iterable;
    public function success(TransportMessage $message): void;
    public function retry(TransportMessage $message, string $errorMessage, \DateTimeInterface $availableAt): void;
    public function failed(TransportMessage $message, string $errorMessage): void;
    public function supportMultiWorker(): bool;
}