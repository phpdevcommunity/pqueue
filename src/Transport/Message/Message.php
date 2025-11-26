<?php

namespace Depo\PQueue\Transport\Message;

use Depo\PQueue\Transport\Envelope;

final class Message
{
    private string $id;
    private Envelope $envelope;

    public function __construct(
        string $id,
        Envelope $envelope
    ) {
        $this->id = $id;
        $this->envelope = $envelope;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getAttempts(): int
    {
        return $this->envelope->getAttempts();
    }

    public function isRetry(): bool
    {
        return $this->envelope->isRetry();
    }
}
