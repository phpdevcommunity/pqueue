<?php

namespace Depo\PQueue\HandlerResolver;

/**
 * Defines the contract for resolving a handler class string to an actual service instance.
 * This allows integration with DI containers and service locators.
 */
interface HandlerResolverInterface
{
    /**
     * Gets a handler instance from its class name.
     *
     * @param string $handlerClassName The fully qualified class name of the handler.
     * @return object The handler service instance.
     * @throws \Psr\Container\NotFoundExceptionInterface No handler was found for the given class name.
     * @throws \Psr\Container\ContainerExceptionInterface Error while retrieving the handler.
     */
    public function getHandler(string $handlerClassName): object;

    /**
     * Checks if a handler for the given class name is available.
     *
     * @param string $handlerClassName The fully qualified class name of the handler.
     * @return bool True if the handler is available, false otherwise.
     */
    public function hasHandler(string $handlerClassName): bool;
}
