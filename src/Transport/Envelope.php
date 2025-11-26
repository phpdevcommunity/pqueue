<?php

namespace Depo\PQueue\Transport;

use DateTimeImmutable;
use DateTimeInterface;

class Envelope
{
    private string $body;
    private bool $retry;
    private int $attempts;
    private ?DateTimeInterface $availableAt;
    private ?DateTimeInterface $lastFailureAt;
    private ?string $errorMessage;

    public function __construct(
        string $body,
        bool $retry,
        int $attempts = 0,
        ?DateTimeInterface $availableAt = null,
        ?DateTimeInterface $lastFailureAt = null,
        ?string $errorMessage = null
    ) {
        $this->body = $body;
        $this->retry = $retry;
        $this->attempts = $attempts;
        $this->availableAt = $availableAt;
        $this->lastFailureAt = $lastFailureAt;
        $this->errorMessage = $errorMessage;
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

    public function getAvailableAt(): ?DateTimeInterface
    {
        return $this->availableAt;
    }

    public function getLastFailureAt(): ?DateTimeInterface
    {
        return $this->lastFailureAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function toArray(): array
    {
        return [
            'body' => $this->getBody(),
            'retry' => $this->isRetry(),
            'attempts' => $this->getAttempts(),
            'availableAt' => $this->getAvailableAt() ? $this->getAvailableAt()->format('Y-m-d H:i:s') : null,
            'lastFailureAt' => $this->getLastFailureAt() ? $this->getLastFailureAt()->format('Y-m-d H:i:s') : null,
            'errorMessage' => $this->getErrorMessage(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['body'],
            (bool) $data['retry'],
            (int) ($data['attempts'] ?? 0),
            isset($data['availableAt']) ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['availableAt']) : null,
            isset($data['lastFailureAt']) ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['lastFailureAt']) : null,
            $data['errorMessage'] ?? null
        );
    }
}
