<?php

namespace Test\Depo\PQueue;

use DateTimeImmutable;
use Depo\UniTester\TestCase;
use Depo\PQueue\Transport\Envelope;

class MessageEnvelopeTest extends TestCase
{
    protected function execute(): void
    {
        $this->testConstructAndGetters();
        $this->testToArrayAndFromArray();
    }

    public function testConstructAndGetters()
    {
        $body = 'test_body';
        $retry = true;
        $attempts = 1;
        $availableAt = new DateTimeImmutable();
        $lastFailureAt = new DateTimeImmutable();
        $errorMessage = 'error';

        $envelope = new Envelope(
            $body,
            $retry,
            $attempts,
            $availableAt,
            $lastFailureAt,
            $errorMessage
        );

        $this->assertEquals($body, $envelope->getBody());
        $this->assertEquals($retry, $envelope->isRetry());
        $this->assertEquals($attempts, $envelope->getAttempts());
        $this->assertEquals($availableAt, $envelope->getAvailableAt());
        $this->assertEquals($lastFailureAt, $envelope->getLastFailureAt());
        $this->assertEquals($errorMessage, $envelope->getErrorMessage());
    }

    public function testToArrayAndFromArray()
    {
        $body = 'test_body';
        $retry = true;
        $attempts = 2;
        $availableAt = new DateTimeImmutable('2023-01-01 10:00:00');
        $lastFailureAt = new DateTimeImmutable('2023-01-01 11:00:00');
        $errorMessage = 'failure';

        $envelope = new Envelope(
            $body,
            $retry,
            $attempts,
            $availableAt,
            $lastFailureAt,
            $errorMessage
        );

        $array = $envelope->toArray();

        $this->assertEquals($body, $array['body']);
        $this->assertEquals($retry, $array['retry']);
        $this->assertEquals($attempts, $array['attempts']);
        $this->assertEquals($availableAt->format('Y-m-d H:i:s'), $array['availableAt']);
        $this->assertEquals($lastFailureAt->format('Y-m-d H:i:s'), $array['lastFailureAt']);
        $this->assertEquals($errorMessage, $array['errorMessage']);

        $newEnvelope = Envelope::fromArray($array);

        $this->assertEquals($envelope->getBody(), $newEnvelope->getBody());
        $this->assertEquals($envelope->isRetry(), $newEnvelope->isRetry());
        $this->assertEquals($envelope->getAttempts(), $newEnvelope->getAttempts());
        $this->assertEquals($envelope->getAvailableAt()->getTimestamp(), $newEnvelope->getAvailableAt()->getTimestamp());
        $this->assertEquals($envelope->getLastFailureAt()->getTimestamp(), $newEnvelope->getLastFailureAt()->getTimestamp());
        $this->assertEquals($envelope->getErrorMessage(), $newEnvelope->getErrorMessage());
    }

    protected function setUp(): void
    {
        // TODO: Implement setUp() method.
    }

    protected function tearDown(): void
    {
        // TODO: Implement tearDown() method.
    }
}
