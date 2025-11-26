<?php

namespace Depo\PQueue;

use LogicException;
use Depo\PQueue\HandlerResolver\HandlerResolverInterface;

final class PQueueConsumerFactory
{
    private HandlerResolverInterface $handlerResolver;
    private array $handlerSources;
    private ?string $handlerCacheDir;

    public function __construct(
        HandlerResolverInterface $handlerResolver,
        array $handlerSources,
        ?string $handlerCacheDir = null
    ) {
        $this->handlerResolver = $handlerResolver;
        $this->handlerSources = $handlerSources;
        $this->handlerCacheDir = $handlerCacheDir;
    }

    public function createConsumer(): PQueueConsumer
    {
        if (empty($this->handlerSources)) {
            throw new LogicException('PQueueConsumerFactory requires at least one handler source.');
        }

        $finder = new PQueueHandlerFinder($this->handlerSources, $this->handlerCacheDir);
        $handlerMap = $finder->find();

        $handlers = [];
        foreach ($handlerMap as $messageClass => $handlerClass) {
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
