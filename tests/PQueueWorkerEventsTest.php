<?php

namespace Test\Depo\PQueue;

use Depo\PQueue\PQueueConsumer;
use Depo\PQueue\PQueueWorker;
use Depo\PQueue\Serializer\MessageSerializer;
use Depo\PQueue\Transport\Envelope;
use Depo\PQueue\Transport\FilesystemTransport;
use Depo\PQueue\Transport\Message\Message;
use Depo\UniTester\TestCase;
use Test\Depo\PQueue\Extra\TestMessage;

class PQueueWorkerEventsTest extends TestCase
{
    private string $transportDir;

    protected function setUp(): void
    {
        $this->transportDir = sys_get_temp_dir() . '/pqueue_test_events_' . uniqid();
        if (!is_dir($this->transportDir)) {
            mkdir($this->transportDir);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->transportDir)) {
            $this->recursiveRemove($this->transportDir);
        }
    }

    protected function execute(): void
    {
        $this->testEvents();
    }

    private function recursiveRemove(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testEvents()
    {
        $transport = new FilesystemTransport($this->transportDir);
        $consumer = new PQueueConsumer([]);

        // Add a message to the queue
        $envelope = new Envelope(
            MessageSerializer::serialize(new TestMessage()),
            true,
            0,
            null
        );
        $transport->send($envelope);

        $options = [
            'stopWhenEmpty' => true,
            'idleSleepMs' => 100,
            'maxMemory' => 128,
            'maxRuntimeSeconds' => 60,
            'maxRetryAttempts' => 3,
            'initialRetryDelayMs' => 1000,
            'retryBackoffMultiplier' => 3,
            'messageDelayMs' => 0,
        ];

        $worker = new PQueueWorker($transport, $consumer, $options);
        $failed = false;
        $stopped = false;

        $worker->onFailure(function ($msg) use (&$failed) {
            $failed = true;
        });

        $worker->onStop(function () use (&$stopped) {
            $stopped = true;
        });

        $worker->run();

        $this->assertTrue($failed, 'onFailure callback should be failed');
        $this->assertTrue($stopped, 'onStop callback should be called');
    }

}
