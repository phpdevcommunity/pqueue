<?php

namespace PhpDevCommunity\PQueue\Transport\Message;

final class TransportMessage
{
    private string $id;
    private string $body;
    private bool $retry;
    private int $attempts;

    public function __construct(
        string $id,
        string $body,
        bool   $retry,
        int    $attempts

    )
    {

        $this->id = $id;
        $this->body = $body;
        $this->retry = $retry;
        $this->attempts = $attempts;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function isRetry(): bool
    {
        return $this->retry;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

}