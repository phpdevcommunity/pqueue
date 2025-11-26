<?php

namespace Test\Depo\PQueue;

use Depo\UniTester\TestCase;
use Depo\PQueue\PQueueConsumer;
use LogicException;
use Test\Depo\PQueue\Extra\TestMessage;
use Test\Depo\PQueue\Extra\TestMessageHandler;

class PQueueConsumerSimpleTest extends TestCase
{
    public function execute(): void
    {
        $this->testConsumeCallsHandler();
        $this->testConsumeThrowsExceptionIfNoHandlerFound();
        $this->testConstructorValidatesHandlers();
    }

    private function testConsumeCallsHandler()
    {
        // Arrange
        $message = new TestMessage();
        $handlerCalled = false;
        $mockHandler = new class extends TestMessageHandler {
            public $called = false;
            public function __invoke(TestMessage $message) {
                $this->called = true;
            }
        };

        $consumer = new PQueueConsumer([
            TestMessage::class => $mockHandler
        ]);

        // Act
        $consumer->consume($message);

        // Assert
        $this->assertTrue($mockHandler->called, 'Handler should have been called.');
    }

    private function testConsumeThrowsExceptionIfNoHandlerFound()
    {
        // Arrange
        $message = new TestMessage();
        $consumer = new PQueueConsumer([]); // Consumer with no handlers

        // Assert
        $this->expectException(\RuntimeException::class, function () use ($consumer, $message) {
            // Act
            $consumer->consume($message);
        });
    }

    private function testConstructorValidatesHandlers()
    {
        // Test invalid handler (not an object)
        $this->expectException(\InvalidArgumentException::class, function () {
            new PQueueConsumer([TestMessage::class => 'not_an_object']);
        });

        // Test invalid handler (no __invoke)
        $this->expectException(\InvalidArgumentException::class, function () {
            new PQueueConsumer([TestMessage::class => new \stdClass()]);
        });

        // Test unknown payload class
        $this->expectException(\InvalidArgumentException::class, function () {
            $mockHandler = new class { public function __invoke(TestMessage $message) {} };
            new PQueueConsumer(['NonExistentClass' => $mockHandler]);
        });
    }

    protected function setUp(): void
    {
        // TODO: Implement setUp() method.
    }

    protected function tearDown(): void
    {
        // TODO: Implement tearDown() method.
    }
}
