<?php

namespace Depo\PQueue\Command;

use Depo\PQueue\PQueueConsumer;
use Depo\PQueue\PQueueWorker;
use Depo\PQueue\Transport\TransportInterface;
use PhpDevCommunity\Console\Command\CommandInterface;
use PhpDevCommunity\Console\InputInterface;
use PhpDevCommunity\Console\Option\CommandOption;
use PhpDevCommunity\Console\Output\ConsoleOutput;
use PhpDevCommunity\Console\OutputInterface;


class PQueueWorkerRunCommand implements CommandInterface
{
    private PQueueConsumer $consumer;
    private TransportInterface $transport;

    public function __construct(
        PQueueConsumer $consumer,
        TransportInterface $transport
    ) {
        $this->consumer = $consumer;
        $this->transport = $transport;
    }

    public function getName(): string
    {
        return 'pqueue:worker:run';
    }

    public function getDescription(): string
    {
        return 'Run a worker to process queue tasks';
    }

    public function getOptions(): array
    {
        return [
            CommandOption::flag('stop-when-empty', 's', 'Stop the worker if the queue is empty'),
            CommandOption::withValue('memory-limit', 'm', 'The memory limit in megabytes (e.g., 128)', 128),
            CommandOption::withValue('time-limit', 't', 'The maximum runtime in seconds (e.g., 3600 for 1 hour)', 3600),
            CommandOption::withValue('sleep', null, 'Time in seconds to sleep if the queue is empty (e.g., 3)', 10),
            CommandOption::withValue('max-retries', null, 'Maximum number of retries for a failed message (e.g., 5)', 3),
            CommandOption::withValue('retry-delay', null, 'Initial delay in seconds before retrying a failed message (e.g., 60 for 1 minute)', 60),
            CommandOption::withValue('retry-multiplier', null, 'Multiplier for exponential backoff between retries (e.g., 3)', 3),
            CommandOption::withValue('message-delay', null, 'Delay in milliseconds between processing each message (e.g., 200)', 200),
        ];
    }

    public function getArguments(): array
    {
        return [];
    }

    public function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new ConsoleOutput($output);
        $io->writeln(' [OK] Worker started. Press Ctrl+C to stop.');
        $io->writeln('');
        $io->title('Configuration');

        $workerOptions = [
            'Stop when empty' => (bool)$input->getOptionValue('stop-when-empty') ? 'Yes' : 'No',
            'Idle sleep' => (int)($input->getOptionValue('sleep')) . ' s',
            'Memory limit' => (int)($input->getOptionValue('memory-limit')) . ' MB',
            'Max runtime' => (int)($input->getOptionValue('time-limit')) . ' s',
            'Max retries' => (int)($input->getOptionValue('max-retries')),
            'Retry delay' => (int)($input->getOptionValue('retry-delay')) . ' s',
            'Retry multiplier' => (int)($input->getOptionValue('retry-multiplier')),
            'Message delay' => (int)($input->getOptionValue('message-delay')) . ' ms',
        ];

        foreach ($workerOptions as $key => $value) {
            $io->writeln(sprintf(" %-20s : %s", $key, $value));
        }
        $io->writeln('');

        $options = [
            'stopWhenEmpty' => (bool)$input->getOptionValue('stop-when-empty'),
            'idleSleepMs' => (int)($input->getOptionValue('sleep')  * 1000),
            'maxMemory' => (int)($input->getOptionValue('memory-limit')),
            'maxRuntimeSeconds' => (int)($input->getOptionValue('time-limit')),
            'maxRetryAttempts' => (int)($input->getOptionValue('max-retries')),
            'initialRetryDelayMs' => (int)($input->getOptionValue('retry-delay')) * 1000,
            'retryBackoffMultiplier' => (int)($input->getOptionValue('retry-multiplier')),
            'messageDelayMs' => (int)($input->getOptionValue('message-delay')),
        ];

        // Use the factory to create and run the worker
        $worker = new PQueueWorker($this->transport, $this->consumer, $options);
        $worker->onConsume(function ($msg) use ($io) {
            $io->writeln(sprintf(" [OK] Message consumed: %s", $msg->getId()));
        });

        $worker->onStop(function () use ($io) {
            $io->writeln(' [INFO] Worker stopped.');
        });

        $io->writeln(' [INFO] Waiting for messages...');
        $worker->run();
    }
}
