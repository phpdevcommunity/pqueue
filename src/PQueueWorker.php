<?php

namespace PhpDevCommunity\PQueue;

use DateTimeImmutable;
use PhpDevCommunity\PQueue\Serializer\MessageSerializer;
use PhpDevCommunity\PQueue\Transport\Message\TransportMessage;
use PhpDevCommunity\PQueue\Transport\TransportInterface;
use Throwable;

final class PQueueWorker
{
    private TransportInterface $transport;
    private PQueueConsumer $consumer;

    /** @var int Time in milliseconds to sleep when no message is available */
    private int $idleSleepMs;

    // Retry-related options
    /** @var int Initial delay in milliseconds before retrying a failed message */
    private int $initialRetryDelayMs;

    /** @var int Maximum number of retry attempts for a failed message */
    private int $maxRetryAttempts;

    /** @var int Multiplier for exponential backoff between retries */
    private int $retryBackoffMultiplier;

    // Resource / runtime limits
    /** @var int Maximum memory usage in bytes before gracefully stopping */
    private int $maxMemoryBytes;

    /** @var int Maximum runtime in seconds before gracefully stopping */
    private int $maxRuntimeSeconds;

    /** @var int Delay in milliseconds between processing each message */
    private int $messageDelayMs;

    /**
     * @param TransportInterface $transport Transport implementation to pull messages from
     * @param PQueueConsumer $consumer
     * @param array{
     *     idleSleepMs?: int,
     *     initialRetryDelayMs?: int,
     *     maxRetryAttempts?: int,
     *     retryBackoffMultiplier?: int,
     *     maxMemoryBytes?: int,
     *     maxRuntimeSeconds?: int,
     *     messageDelayMs?: int
     * } $options Worker options
     */
    public function __construct(TransportInterface $transport, PQueueConsumer $consumer, array $options)
    {
        $this->transport = $transport;
        $this->consumer = $consumer;

        // Sleep / idle
        $this->idleSleepMs = $options['idleSleepMs'] ?? 1000;

        // Retry options
        $this->initialRetryDelayMs = $options['initialRetryDelayMs'] ?? 60000; // 1 min
        $this->maxRetryAttempts = $options['maxRetryAttempts'] ?? 5;
        $this->retryBackoffMultiplier = $options['retryBackoffMultiplier'] ?? 3;

        // Resource / runtime limits
        $this->maxMemoryBytes = ($options['maxMemoryBytes'] ?? 100) * 1024 * 1024; // 100 MB
        $this->maxRuntimeSeconds = $options['maxRuntimeSeconds'] ?? 3600; // 1 hour

        // Message delay
        $this->messageDelayMs = $options['messageDelayMs'] ?? 0; // default 0 ms
        $this->validateOptions();
    }

    public function run(): void
    {
        $startTime = time();

        while (true) {
            $hasMessages = false;
            foreach ($this->transport->getNextAvailableMessages() as $msg) {
                $hasMessages = true;
                try {
                    if (!$msg instanceof TransportMessage) {
                        throw new \RuntimeException(sprintf(
                            'Worker expected an instance of Message from transport "%s", got "%s".',
                            get_class($this->transport),
                            is_object($msg) ? get_class($msg) : gettype($msg)
                        ));
                    }
                    $payload = MessageSerializer::unSerialize($msg->getBody());
                    $this->consumer->consume($payload);
                    $this->transport->success($msg->getId());
                } catch (Throwable $e) {
                    $attempts = $msg->getAttempts() + 1;
                    if ($msg->isRetry() && $attempts <= $this->maxRetryAttempts) {
                        $delay = $this->initialRetryDelayMs * pow($this->retryBackoffMultiplier, ($attempts - 1));
                        $availableAt = (new DateTimeImmutable())->modify("+{$delay} milliseconds");
                        $this->transport->retry($msg->getId(), $e->getMessage(), $availableAt);
                    } else {
                        $this->transport->failed($msg->getId(), $e->getMessage());
                    }
                }

                if (memory_get_usage(true) > $this->maxMemoryBytes) {
                    break;
                }

                if ((time() - $startTime) > $this->maxRuntimeSeconds) {
                    break;
                }

                if ($this->messageDelayMs > 0) {
                    usleep($this->messageDelayMs * 1000);
                }
            }

            if (!$hasMessages) {
                usleep($this->idleSleepMs * 1000);
            }
        }
    }

    private function validateOptions(): void
    {
        foreach ([
                     'idleSleepMs' => $this->idleSleepMs,
                     'initialRetryDelayMs' => $this->initialRetryDelayMs,
                     'retryBackoffMultiplier' => $this->retryBackoffMultiplier,
                     'maxMemoryBytes' => $this->maxMemoryBytes,
                     'maxRuntimeSeconds' => $this->maxRuntimeSeconds,
                 ] as $name => $value) {
            if ($value <= 0) {
                throw new \InvalidArgumentException(sprintf(
                    'Worker option "%s" must be greater than 0, %d given.',
                    $name,
                    $value
                ));
            }
        }

        if ($this->maxRetryAttempts < 0) {
            throw new \InvalidArgumentException(sprintf(
                'Worker option "retryMax" must be >= 0, %d given.',
                $this->maxRetryAttempts
            ));
        }

        if ($this->messageDelayMs < 0) {
            throw new \InvalidArgumentException(sprintf(
                'Worker option "messageDelayMs" must be >= 0, %d given.',
                $this->messageDelayMs
            ));
        }
    }
}