<?php

namespace Test\Depo\PQueue\Command;

use Depo\PQueue\Command\PQueueWorkerRunCommand;
use Depo\PQueue\PQueueConsumer;
use Depo\PQueue\Transport\FilesystemTransport;
use Depo\UniTester\TestCase;
use PhpDevCommunity\Console\CommandParser;
use PhpDevCommunity\Console\CommandRunner;
use PhpDevCommunity\Console\InputInterface;
use PhpDevCommunity\Console\Output;
use PhpDevCommunity\Console\OutputInterface;

class PQueueWorkerRunCommandTest extends TestCase
{
    private string $transportDir;

    protected function setUp(): void
    {
        $this->transportDir = sys_get_temp_dir() . '/pqueue_test_' . uniqid();
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

    private function recursiveRemove(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    protected function execute(): void
    {
        $this->testExecute();
        $this->testExecuteWithAllOptions();
    }

    public function testExecute()
    {
        $transport = new FilesystemTransport($this->transportDir);
        $consumer = new PQueueConsumer([]);

        $runner = new CommandRunner([
            new PQueueWorkerRunCommand($consumer, $transport),
        ]);
        $out = [];
        $code = $runner->run(new CommandParser(['', 'pqueue:worker:run', '--stop-when-empty']), new Output(function ($message) use (&$out) {
            $out[] = $message;
        }));
        $this->assertEquals(0, $code);
        $this->assertCount(44, $out);

    }

    public function testExecuteWithAllOptions()
    {
        $transport = new FilesystemTransport($this->transportDir);
        $consumer = new PQueueConsumer([]);

        $runner = new CommandRunner([
            new PQueueWorkerRunCommand($consumer, $transport),
        ]);
        $out = [];
        $code = $runner->run(new CommandParser([
            '',
            'pqueue:worker:run',
            '--stop-when-empty',
            '--sleep=1',
            '--memory-limit=128',
            '--time-limit=60',
            '--max-retries=1',
            '--retry-delay=1',
            '--retry-multiplier=1',
            '--message-delay=10'
        ]), new Output(function ($message) use (&$out) {
            $out[] = $message;
        }));
        $this->assertEquals(0, $code);
        $this->assertCount(44, $out);

    }
}
