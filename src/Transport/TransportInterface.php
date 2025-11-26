<?php

namespace Depo\PQueue\Transport;

use Depo\PQueue\Transport\Message\Message;

interface TransportInterface
{
    public function send(Envelope $message): void;
    public function getNextAvailableMessages(): iterable;
    public function success(Message $message): void;
    public function retry(Message $message, string $errorMessage, \DateTimeInterface $availableAt): void;
    public function failed(Message $message, string $errorMessage): void;
    public function supportMultiWorker(): bool;
    public static function create(array $options): self;
}
