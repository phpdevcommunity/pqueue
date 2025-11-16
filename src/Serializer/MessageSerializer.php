<?php

namespace PhpDevCommunity\PQueue\Serializer;
final class MessageSerializer
{
    public static function serialize(object $message): string
    {
        return serialize($message);
    }

    public static function unSerialize(string $serializedMessage): object
    {
        $obj = unserialize($serializedMessage);
        if (!is_object($obj)) {
            throw new \InvalidArgumentException('Serialized message is not an object');
        }
        return $obj;
    }
}