<?php

namespace Test\Depo\PQueue\Extra;


class InvalidHandler
{
    public function __invoke(TestMessage $message, bool $anotherArg)
    {
        // Invalid signature
    }
}
