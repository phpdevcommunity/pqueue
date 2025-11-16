<?php

namespace PhpDevCommunity\PQueue;

final class PQueueConsumer
{

    /** @var callable[] List of handlers for processing messages */
    private array $handlers = [];

    /**
     * @param callable[] $handlers List of callable handlers for processing messages
     */
    public function __construct(array $handlers)
    {
        foreach ($handlers as $handler) {
            if (!is_object($handler)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Worker::__construct: Handler must be an object, %s given',
                        gettype($handler)
                    )
                );
            }
            if (!is_callable($handler)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Worker::__construct: Handler object "%s" must implement an __invoke() method',
                        get_class($handler)
                    )
                );
            }
            $this->handlers[] = $handler;
        }
    }

    public function consume(object $payload)
    {
        $payloadClass = get_class($payload);
        $handlerFound = false;
        foreach ($this->handlers as $handler) {
            if (get_class($handler) === sprintf("%sHandler", $payloadClass)) {
                $handler($payload);
                break;
            }
        }

        if (!$handlerFound) {
            throw new \RuntimeException(sprintf(
                'No handler found for payload of class "%s"',
                $payloadClass
            ));
        }
    }

}