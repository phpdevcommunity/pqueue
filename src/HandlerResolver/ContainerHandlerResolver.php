<?php

namespace Depo\PQueue\HandlerResolver;

use Psr\Container\ContainerInterface;

class ContainerHandlerResolver implements HandlerResolverInterface
{
    private ContainerInterface $container;
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getHandler(string $handlerClassName): object
    {
        return $this->container->get($handlerClassName);
    }

    public function hasHandler(string $handlerClassName): bool
    {
        return $this->container->has($handlerClassName);
    }
}
