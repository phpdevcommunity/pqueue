<?php

namespace Test\Depo\PQueue;

use DateTimeImmutable;
use Depo\UniTester\TestCase;
use Depo\PQueue\Transport\FilesystemTransport;
use Depo\PQueue\Transport\Envelope;
use Depo\PQueue\Transport\Message\Message;

class FilesystemTransportTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/pqueue_test_' . uniqid('', true);
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
        mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    protected function execute(): void
    {
        $this->testSendAndGetNext();
        $this->testSuccess();
        $this->testRetry();
        $this->testFailed();
    }

    private function cleanDir()
    {
        $files = glob($this->testDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
    }

    public function testSendAndGetNext()
    {
        $this->cleanDir();
        $transport = new FilesystemTransport($this->testDir);
        $envelope = new Envelope('test_body', true, 0);

        $transport->send($envelope);

        $messages = iterator_to_array($transport->getNextAvailableMessages());

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(Message::class, $messages[0]);
        $this->assertEquals('test_body', $messages[0]->getEnvelope()->getBody());
    }

    public function testSuccess()
    {
        $this->cleanDir();
        $transport = new FilesystemTransport($this->testDir);
        $envelope = new Envelope('test_body', true, 0);
        $transport->send($envelope);

        $messages = iterator_to_array($transport->getNextAvailableMessages());
        $message = $messages[0];

        $transport->success($message);

        $messages = iterator_to_array($transport->getNextAvailableMessages());
        $this->assertCount(0, $messages);
    }

    public function testRetry()
    {
        $this->cleanDir();
        $transport = new FilesystemTransport($this->testDir);
        $envelope = new Envelope('test_body', true, 0);
        $transport->send($envelope);

        $messages = iterator_to_array($transport->getNextAvailableMessages());
        $message = $messages[0];

        $availableAt = (new DateTimeImmutable())->modify('+1 minute');
        $transport->retry($message, 'error', $availableAt);

        // Should not be available immediately
        $messages = iterator_to_array($transport->getNextAvailableMessages());
        $this->assertCount(0, $messages);
    }

    public function testFailed()
    {
        $this->cleanDir();
        $transport = new FilesystemTransport($this->testDir);
        $envelope = new Envelope('test_body', true, 0);
        $transport->send($envelope);

        $messages = iterator_to_array($transport->getNextAvailableMessages());
        $message = $messages[0];

        $transport->failed($message, 'fatal error');

        // Should be moved to failed file, so not available
        $messages = iterator_to_array($transport->getNextAvailableMessages());
        $this->assertCount(0, $messages);

        // Verify failed file exists
        $failedFiles = glob($this->testDir . '/*.failed');
        $this->assertCount(1, $failedFiles);
    }
}
