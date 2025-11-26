<?php

namespace Test\Depo\PQueue;

use Depo\UniTester\TestCase;
use Depo\PQueue\Serializer\MessageSerializer;

class MessageSerializerTest extends TestCase
{
    protected function execute(): void
    {
        $this->testSerializeObject();
        $this->testUnSerializeObject();
    }

    public function testSerializeObject()
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';
        $serialized = MessageSerializer::serialize($obj);

        $this->assertStringContains($serialized, 'foo');
        $this->assertStringContains($serialized, 'bar');
    }

    public function testSerializeArray()
    {
        $arr = ['foo' => 'bar'];
        $serialized = MessageSerializer::serialize($arr);

        $this->assertStringContains($serialized, 'foo');
        $this->assertStringContains($serialized, 'bar');
    }

    public function testUnSerializeObject()
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';
        $serialized = MessageSerializer::serialize($obj);
        $unserialized = MessageSerializer::unSerialize($serialized);

        $this->assertInstanceOf(\stdClass::class, $unserialized);
        $this->assertEquals($obj->foo, $unserialized->foo);
    }

    public function testUnSerializeArray()
    {
        $arr = ['foo' => 'bar'];
        $serialized = MessageSerializer::serialize($arr);
        $unserialized = MessageSerializer::unSerialize($serialized);

        $this->assertTrue(is_array($unserialized));
        $this->assertEquals($arr['foo'], $unserialized['foo']);
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
