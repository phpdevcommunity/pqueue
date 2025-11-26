<?php

namespace Depo\PQueue;

use DateTimeImmutable;
use Depo\PQueue\Serializer\MessageSerializer;
use Depo\PQueue\Transport\Message\Message;
use Depo\PQueue\Transport\TransportInterface;
use PhpDevCommunity\Resolver\Option;
use PhpDevCommunity\Resolver\OptionsResolver;
use Throwable;

final class PQueueWorker
{
    private TransportInterface $transport;
    private PQueueConsumer $consumer;

    private bool $stopWhenEmpty;

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

    private int $startTime;

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
     *     messageDelayMs?: int,
     *     stopWhenEmpty?: bool
     * } $options Worker options
     */
    public function __construct(TransportInterface $transport, PQueueConsumer $consumer, array $options)
    {

        $this->transport = $transport;
        $this->consumer = $consumer;

        $resolver = new OptionsResolver([
            Option::bool('stopWhenEmpty')->setOptional(false),
            Option::int('idleSleepMs')->setOptional(1000)->min(1),
            Option::int('maxRetryAttempts')->setOptional(5)->min(0),
            Option::int('initialRetryDelayMs')->setOptional(60000)->min(0),
            Option::int('retryBackoffMultiplier')->setOptional(3)->min(1),
            Option::int('maxMemory')->setOptional(100)->min(1),
            Option::int('maxRuntimeSeconds')->setOptional(3600)->min(60),
            Option::int('messageDelayMs')->setOptional(0)->min(0),
        ]);
        $optionsResolved = $resolver->resolve($options);


        // Worker options
        $this->stopWhenEmpty = $optionsResolved['stopWhenEmpty'];

        // Sleep / idle
        $this->idleSleepMs = $optionsResolved['idleSleepMs'];

        // Retry options
        $this->initialRetryDelayMs = $optionsResolved['initialRetryDelayMs']; // 1 min
        $this->maxRetryAttempts = $optionsResolved['maxRetryAttempts'];
        $this->retryBackoffMultiplier = $optionsResolved['retryBackoffMultiplier'];

        // Resource / runtime limits
        $this->maxMemoryBytes = $optionsResolved['maxMemory'] * 1024 * 1024;
        $this->maxRuntimeSeconds = $optionsResolved['maxRuntimeSeconds'];

        // Message delay
        $this->messageDelayMs = $optionsResolved['messageDelayMs'];
    }

    public function run(): void
    {
        $this->startTime = time();

        while (true) {
            $hasMessages = false;
            foreach ($this->transport->getNextAvailableMessages() as $msg) {
                $hasMessages = true;
                try {
                    if (!$msg instanceof Message) {
                        throw new \RuntimeException(sprintf(
                            'Worker expected an instance of Message from transport "%s", got "%s".',
                            get_class($this->transport),
                            is_object($msg) ? get_class($msg) : gettype($msg)
                        ));
                    }
                    $payload = MessageSerializer::unSerialize($msg->getEnvelope()->getBody());
                    $this->consumer->consume($payload);
                    $this->transport->success($msg);
                } catch (Throwable $e) {
                    $attempts = $msg->getAttempts() + 1;
                    if ($msg->isRetry() && $attempts <= $this->maxRetryAttempts) {
                        $delay = $this->initialRetryDelayMs * pow($this->retryBackoffMultiplier, ($attempts - 1));
                        $availableAt = (new DateTimeImmutable())->modify("+{$delay} milliseconds");
                        $this->transport->retry($msg, $e->getMessage(), $availableAt);
                    } else {
                        $this->transport->failed($msg, $e->getMessage());
                    }
                }

                if ($this->needToBreak()) {
                    break;
                }

                if ($this->messageDelayMs > 0) {
                    usleep($this->messageDelayMs * 1000);
                }
            }

            if ($this->needToBreak()) {
                break;
            }

            if (!$hasMessages) {
                if ($this->stopWhenEmpty) {
                    break;
                }
                usleep($this->idleSleepMs * 1000);
            }
        }
    }



    private function needToBreak(): bool
    {
        if (memory_get_usage(true) > $this->maxMemoryBytes) {
            return true;
        }

        if ((time() - $this->startTime) > $this->maxRuntimeSeconds) {
            return true;
        }

        return false;
    }
}
