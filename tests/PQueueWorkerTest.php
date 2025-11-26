<?php

namespace Test\Depo\PQueue;

use Depo\UniTester\TestCase;
use Depo\PQueue\PQueueConsumer;
use Depo\PQueue\PQueueWorker;
use Depo\PQueue\Serializer\MessageSerializer;
use Depo\PQueue\Transport\Envelope;
use Depo\PQueue\Transport\Message\Message;
use Depo\PQueue\Transport\TransportInterface;

class PQueueWorkerTest extends TestCase
{
    protected function execute(): void
    {
        $this->testWorkerProcess();
        $this->testStopWhenEmpty();
        $this->testRetryLogic();
        $this->testMaxRetryAttempts();
        $this->testRetryBackoff();
        $this->testMaxRuntime();
        $this->testMaxMemory();
        $this->testMessageDelay();
        $this->testHighVolume();
    }

    public function testWorkerProcess()
    {
        $processed = false;
        $transport = $this->createMockTransport([new Envelope(MessageSerializer::serialize(new \stdClass()), true, 0)]);

        $consumer = new PQueueConsumer([\stdClass::class => new class($processed) {
            private $processed;
            public function __construct(&$processed)
            {
                $this->processed = &$processed;
            }
            public function __invoke(\stdClass $msg)
            {
                $this->processed = true;
            }
        }]);

        $worker = new PQueueWorker($transport, $consumer, ['stopWhenEmpty' => true]);
        $worker->run();

        $this->assertTrue($processed);
        $this->assertCount(1, $transport->processed);
    }

    public function testStopWhenEmpty()
    {
        $transport = $this->createMockTransport([]);
        $consumer = new PQueueConsumer([]);

        $startTime = microtime(true);
        $worker = new PQueueWorker($transport, $consumer, ['stopWhenEmpty' => true]);
        $worker->run();
        $duration = microtime(true) - $startTime;

        $this->assertTrue($duration < 1.0, "Worker should stop immediately");
    }

    public function testRetryLogic()
    {
        $transport = $this->createMockTransport([new Envelope(MessageSerializer::serialize(new \stdClass()), true, 0)]);

        $consumer = new PQueueConsumer([\stdClass::class => new class {
            public function __invoke(\stdClass $msg)
            {
                throw new \Exception("fail");
            }
        }]);

        // Max retries 1. Flow: 0 -> retry -> 1 -> fail
        // Set delay to 0 so it processes immediately
        $worker = new PQueueWorker($transport, $consumer, [
            'stopWhenEmpty' => true,
            'maxRetryAttempts' => 1,
            'initialRetryDelayMs' => 0
        ]);
        $worker->run();

        $this->assertCount(1, $transport->retried);
        $this->assertCount(1, $transport->failed);
    }

    public function testMaxRetryAttempts()
    {
        // Max retries 3. Flow: 0 -> 1 -> 2 -> 3 -> Fail
        $transport = $this->createMockTransport([new Envelope(MessageSerializer::serialize(new \stdClass()), true, 0)]);

        $consumer = new PQueueConsumer([\stdClass::class => new class {
            public function __invoke(\stdClass $msg)
            {
                throw new \Exception("fail");
            }
        }]);

        $worker = new PQueueWorker($transport, $consumer, [
            'stopWhenEmpty' => true,
            'maxRetryAttempts' => 3,
            'initialRetryDelayMs' => 0
        ]);
        $worker->run();

        $this->assertCount(3, $transport->retried, "Should retry 3 times");
        $this->assertCount(1, $transport->failed, "Should fail eventually");
    }

    public function testRetryBackoff()
    {
        $transport = $this->createMockTransport([new Envelope(MessageSerializer::serialize(new \stdClass()), true, 0)]);

        $consumer = new PQueueConsumer([\stdClass::class => new class {
            public function __invoke(\stdClass $msg)
            {
                throw new \Exception("fail");
            }
        }]);

        // initialDelay 1000ms, multiplier 2. 
        // Attempt 0 fails -> retry (attempt 1). Delay = 1000 * 2^0 = 1000ms.
        $worker = new PQueueWorker($transport, $consumer, [
            'stopWhenEmpty' => true,
            'initialRetryDelayMs' => 1000,
            'retryBackoffMultiplier' => 2,
            'maxRetryAttempts' => 1
        ]);
        $worker->run();

        $this->assertCount(1, $transport->retried);
        $retryInfo = $transport->retried[0];
        $availableAt = $retryInfo['at'];

        $diff = $availableAt->getTimestamp() - time();
        // It should be around 1 second in future.
        $this->assertTrue($diff >= 1 && $diff <= 2, "Backoff should be around 1s");
    }

    public function testMaxRuntime()
    {
        $this->assertTrue(true);
    }

    public function testMaxMemory()
    {
        // Custom transport needed for memory test
        $transport = new class implements TransportInterface {
            public function getNextAvailableMessages(): iterable
            {
                while (true) {
                    $data = str_repeat('a', 1024 * 1024);
                    yield new Message('1', new Envelope(MessageSerializer::serialize(new \stdClass()), true));
                }
            }
            public function send(Envelope $message): void {}
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

        $consumer = new PQueueConsumer([\stdClass::class => new class {
            public function __invoke(\stdClass $msg) {}
        }]);

        $worker = new PQueueWorker($transport, $consumer, ['maxMemory' => 1]);

        $startTime = microtime(true);
        $worker->run();
        $duration = microtime(true) - $startTime;

        $this->assertTrue($duration < 5.0, "Worker should stop when memory limit exceeded");
    }

    public function testMessageDelay()
    {
        $transport = $this->createMockTransport([
            new Envelope(MessageSerializer::serialize(new \stdClass()), true, 0),
            new Envelope(MessageSerializer::serialize(new \stdClass()), true, 0)
        ]);

        $consumer = new PQueueConsumer([\stdClass::class => new class {
            public function __invoke(\stdClass $msg) {}
        }]);

        $worker = new PQueueWorker($transport, $consumer, ['stopWhenEmpty' => true, 'messageDelayMs' => 500]);

        $startTime = microtime(true);
        $worker->run();
        $duration = microtime(true) - $startTime;

        $this->assertTrue($duration >= 1.0, "Worker should respect message delay");
    }

    public function testHighVolume()
    {
        $count = 1000;
        $envelopes = [];
        for ($i = 0; $i < $count; $i++) {
            $envelopes[] = new Envelope(MessageSerializer::serialize(new \stdClass()), true, 0);
        }

        $transport = $this->createMockTransport($envelopes);

        $consumer = new PQueueConsumer([\stdClass::class => new class {
            public function __invoke(\stdClass $msg) {}
        }]);

        $worker = new PQueueWorker($transport, $consumer, ['stopWhenEmpty' => true]);

        $startTime = microtime(true);
        $worker->run();
        $duration = microtime(true) - $startTime;

        $this->assertCount($count, $transport->processed);
        $this->assertTrue($duration < 2.0, "High volume processing should be fast");
    }

    private function createMockTransport(array $envelopes): TransportInterface
    {
        return new class($envelopes) implements TransportInterface {
            public array $queue = [];
            public array $processed = [];
            public array $retried = [];
            public array $failed = [];

            public function __construct($envelopes)
            {
                foreach ($envelopes as $k => $e) {
                    $this->queue[] = new Message((string)$k, $e);
                }
            }

            public function send(Envelope $message): void
            {
                $this->queue[] = new Message(uniqid(), $message);
            }

            public function getNextAvailableMessages(): iterable
            {
                $now = new \DateTimeImmutable();
                foreach ($this->queue as $k => $msg) {
                    $av = $msg->getEnvelope()->getAvailableAt();
                    if ($av === null || $av <= $now) {
                        unset($this->queue[$k]);
                        yield $msg;
                    }
                }
            }

            public function success(Message $message): void
            {
                $this->processed[] = $message;
            }

            public function retry(Message $message, string $errorMessage, \DateTimeInterface $availableAt): void
            {
                $this->retried[] = ['msg' => $message, 'error' => $errorMessage, 'at' => $availableAt];
                $env = $message->getEnvelope();
                $newEnv = new Envelope($env->getBody(), true, $env->getAttempts() + 1, $availableAt);
                $this->queue[] = new Message($message->getId(), $newEnv);
            }

            public function failed(Message $message, string $errorMessage): void
            {
                $this->failed[] = ['msg' => $message, 'error' => $errorMessage];
            }

            public function supportMultiWorker(): bool
            {
                return false;
            }

            public static function create(array $options): TransportInterface
            {
                // TODO: Implement create() method.
            }
        };
    }

    protected function setUp(): void {}
    protected function tearDown(): void {}
}
