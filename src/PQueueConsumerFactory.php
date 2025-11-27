<?php

namespace Depo\PQueue;

use LogicException;
use Depo\PQueue\HandlerResolver\HandlerResolverInterface;

final class PQueueConsumerFactory
{
    private HandlerResolverInterface $handlerResolver;
    private array $handlerMap;

    /**
     * @param HandlerResolverInterface $handlerResolver
     * @param array $handlerMap Map of message classes to handler classes
     */
    public function __construct(
        HandlerResolverInterface $handlerResolver,
        array $handlerMap
    ) {
        $this->handlerResolver = $handlerResolver;
        $this->handlerMap = $handlerMap;
    }

    public function createConsumer(): PQueueConsumer
    {
        if (empty($this->handlerMap)) {
            throw new LogicException('PQueueConsumerFactory requires at least one handler to be registered.');
        }

        $handlers = [];
        foreach ($this->handlerMap as $messageClass => $handlerClass) {
            if (!$this->handlerResolver->hasHandler($handlerClass)) {
                throw new LogicException(sprintf(
                    'Message handler "%s" was found by the finder, but it is not registered as a service in the container (or the resolver cannot find it).',
                    $handlerClass
                ));
            }
            $handlerInstance = $this->handlerResolver->getHandler($handlerClass);

            if (!is_object($handlerInstance)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'PQueueConsumerFactory: Resolved handler for message "%s" must be an object, %s given.',
                        $messageClass,
                        gettype($handlerInstance)
                    )
                );
            }
            if (!is_callable($handlerInstance)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'PQueueConsumerFactory: Resolved handler object "%s" for message "%s" must implement an __invoke() method.',
                        get_class($handlerInstance),
                        $messageClass
                    )
                );
            }
            if (!class_exists($messageClass)) {
                throw new \InvalidArgumentException(
                    sprintf('PQueueConsumerFactory: Unknown payload class "%s" for handler "%s".', $messageClass, get_class($handlerInstance))
                );
            }
            $handlers[$messageClass] = $handlerInstance;
        }

        return new PQueueConsumer($handlers);
    }
}
