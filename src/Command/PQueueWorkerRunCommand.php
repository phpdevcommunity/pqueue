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
            CommandOption::withValue('sleep', null, 'Time in seconds to sleep if the queue is empty (e.g., 3)',10),
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
        $io->title('PQueue Worker Run Command');
        $io->listKeyValues($input->getOptions());
        $io->writeln('');
        $workerOptions = [
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
        $worker = new PQueueWorker($this->transport, $this->consumer, $workerOptions);
        $worker->run();
    }
}
