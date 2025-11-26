<?php

namespace Depo\PQueue;

final class PQueueConsumer
{
    /** @var callable[] List of handlers for processing messages */
    private array $handlers = [];

    /**
     * @param callable[] $handlers List of callable handlers for processing messages
     */
    public function __construct(array $handlers)
    {
        foreach ($handlers as $payloadClass => $handler) {
            if (!is_object($handler)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'PQueueConsumer: Handler must be an object, %s given',
                        gettype($handler)
                    )
                );
            }
            if (!is_callable($handler)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'PQueueConsumer: Handler object "%s" must implement an __invoke() method',
                        get_class($handler)
                    )
                );
            }
            if (!class_exists($payloadClass)) {
                throw new \InvalidArgumentException(
                    sprintf('PQueueConsumer: Unknown payload class "%s"', $payloadClass)
                );
            }
            $this->handlers[$payloadClass] = $handler;
        }
    }

    public function consume(object $payload)
    {
        $payloadClass = get_class($payload);
        if (!isset($this->handlers[$payloadClass])) {
            throw new \RuntimeException(sprintf(
                'No handler found for payload of class "%s".',
                $payloadClass
            ));
        }
        $handler = $this->handlers[$payloadClass];
        $handler($payload);
    }
}
