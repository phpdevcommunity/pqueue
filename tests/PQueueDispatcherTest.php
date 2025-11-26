<?php

namespace Test\Depo\PQueue;

use DateTimeImmutable;
use Depo\UniTester\TestCase;
use Depo\PQueue\PQueueDispatcher;
use Depo\PQueue\Transport\Envelope;
use Depo\PQueue\Transport\Message\Message;
use Depo\PQueue\Transport\TransportInterface;

class PQueueDispatcherTest extends TestCase
{
    protected function execute(): void
    {
        $this->testDispatch();
    }

    public function testDispatch()
    {
        $transport = new class implements TransportInterface {
            public ?Envelope $lastEnvelope = null;
            public function send(Envelope $message): void
            {
                $this->lastEnvelope = $message;
            }
            public function getNextAvailableMessages(): iterable
            {
                return [];
            }
            public function success(Message $message): void {}
            public function retry(Message $message, string $errorMessage, \DateTimeInterface $availableAt): void {}
            public function failed(Message $message, string $errorMessage): void {}
            public function supportMultiWorker(): bool
            {
                return false;
            }

            public static function create(array $options): TransportInterface
            {
                // TODO: Implement create() method.
            }
        };

        $dispatcher = new PQueueDispatcher($transport);
        $message = new \stdClass();
        $message->data = 'test';

        $dispatcher->dispatch($message);

        $this->assertNotNull($transport->lastEnvelope);
        $this->assertStringContains($transport->lastEnvelope->getBody(), 'test');
        $this->assertTrue($transport->lastEnvelope->isRetry());
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
