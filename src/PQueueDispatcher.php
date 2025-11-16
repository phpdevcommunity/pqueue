<?php

namespace PhpDevCommunity\PQueue;

use PhpDevCommunity\PQueue\Serializer\MessageSerializer;
use PhpDevCommunity\PQueue\Transport\TransportInterface;

final class PQueueDispatcher
{

    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }


    /**
     * Dispatch a message object into the transport.
     *
     * @param object $message The message object to queue
     * @param \DateTimeInterface|null $availableAt When the message becomes available
     * @param bool $retry Whether the message is retryable
     */
    public function dispatch(object $message, ?\DateTimeInterface $availableAt = null, bool $retry = true): void
    {
        if ($availableAt === null) {
            $availableAt = new \DateTimeImmutable();
        }

        $this->transport->send(MessageSerializer::serialize($message), $availableAt, $retry);
    }

}