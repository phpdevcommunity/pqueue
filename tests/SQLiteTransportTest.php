<?php

namespace Test\Depo\PQueue;

use DateTimeImmutable;
use Depo\UniTester\TestCase;
use Depo\PQueue\Transport\SQLiteTransport;
use Depo\PQueue\Transport\Envelope;
use Depo\PQueue\Transport\Message\Message;

class SQLiteTransportTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/pqueue_test_' . uniqid('', true) . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    protected function execute(): void
    {
        $this->testSendAndGetNext();
        $this->testSuccess();
        $this->testRetry();
        $this->testFailed();
    }

    private function cleanDb()
    {
        $db = new \SQLite3($this->dbPath);
        $db->exec('DELETE FROM pqueue_messages');
        $db->close();
    }

    public function testSendAndGetNext()
    {
        $transport = new SQLiteTransport($this->dbPath);
        $envelope = new Envelope('test_body', true, 0);

        $transport->send($envelope);

        $messages = iterator_to_array($transport->getNextAvailableMessages());

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(Message::class, $messages[0]);
        $this->assertEquals('test_body', $messages[0]->getEnvelope()->getBody());
    }

    public function testSuccess()
    {
        $this->cleanDb();
        $transport = new SQLiteTransport($this->dbPath);
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
        $this->cleanDb();
        $transport = new SQLiteTransport($this->dbPath);
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
        $this->cleanDb();
        $transport = new SQLiteTransport($this->dbPath);
        $envelope = new Envelope('test_body', true, 0);
        $transport->send($envelope);

        $messages = iterator_to_array($transport->getNextAvailableMessages());
        $message = $messages[0];

        $transport->failed($message, 'fatal error');

        // Should not be available in pending/retry
        $messages = iterator_to_array($transport->getNextAvailableMessages());
        $this->assertCount(0, $messages);
    }
}
