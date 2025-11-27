<?php

namespace Test\Depo\PQueue;

use Depo\PQueue\PQueueHandlerFinder;
use Depo\UniTester\TestCase;
use Depo\PQueue\HandlerResolver\ContainerHandlerResolver;
use Depo\PQueue\HandlerResolver\HandlerResolverInterface;
use Depo\PQueue\PQueueConsumer;
use Depo\PQueue\PQueueConsumerFactory;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use LogicException;
use Test\Depo\PQueue\Extra\TestMessage;
use Test\Depo\PQueue\Extra\TestMessageHandler;

// --- Mock Exception for PSR-11 ---
class MockNotFoundException extends \RuntimeException implements NotFoundExceptionInterface {}

class PQueueConsumerFactoryTest extends TestCase
{
    private array $tempDirs = [];

    protected function setUp(): void
    {
        // All mocks and temporary directories will be created within each test method for clarity and isolation.
    }

    protected function tearDown(): void
    {
        // Clean up all temporary directories created during tests.
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->deleteDirectory($dir);
            }
        }
        $this->tempDirs = [];
    }

    // --- Helper Mocks (defined once, used in tests) ---

    /**
     * Creates a mock PSR-11 ContainerInterface.
     * @param bool $withHandler If true, the container will have 'App\MyTestHandler' registered.
     */
    private function createMockContainer(bool $withHandler = true): ContainerInterface
    {
        $container = new class implements ContainerInterface {
            private array $services = [];
            public function get(string $id) {
                if (!array_key_exists($id, $this->services)) { // Use array_key_exists for robustness
                    throw new MockNotFoundException("Service $id not found.");
                }
                return $this->services[$id];
            }
            public function has(string $id): bool { return array_key_exists($id, $this->services); } // Use array_key_exists for robustness
            public function set(string $id, object $service): void { $this->services[$id] = $service; }
        };
        if ($withHandler) {
            // Use a real handler class from your Extra directory for more realistic testing
            $container->set(TestMessageHandler::class, new TestMessageHandler());
        }
        return $container;
    }

    /**
     * Creates a mock HandlerResolverInterface.
     * @param ContainerInterface $container The container to back the resolver.
     */
    private function createMockHandlerResolver(ContainerInterface $container): HandlerResolverInterface
    {
        return new ContainerHandlerResolver($container);
    }

    // --- Test Execution ---

    public function execute(): void
    {
        $this->testFactoryCreatesConsumerSuccessfully();
        $this->testFactoryFailsWithoutHandlerSource();
        $this->testFactoryFailsIfHandlerIsNotInContainer();
    }

    // --- Individual Tests ---

    private function testFactoryCreatesConsumerSuccessfully()
    {
        // Arrange
        $container = $this->createMockContainer(); // Container has the handler
        $resolver = $this->createMockHandlerResolver($container);

        $sourceDir = $this->createTempDir();
        // Copy a real handler and message to the temp source dir
        copy(__DIR__ . '/Extra/TestMessage.php', $sourceDir . '/TestMessage.php');
        copy(__DIR__ . '/Extra/TestMessageHandler.php', $sourceDir . '/TestMessageHandler.php');

        $finder  = new PQueueHandlerFinder([$sourceDir]);
        $factory = new PQueueConsumerFactory($resolver, $finder->find());

        // Act
        $consumer = $factory->createConsumer();

        // Assert
        $this->assertInstanceOf(PQueueConsumer::class, $consumer);
    }

    private function testFactoryFailsWithoutHandlerSource()
    {
        // Arrange
        $container = $this->createMockContainer(false); // No handler needed if source is missing
        $resolver = $this->createMockHandlerResolver($container);

        // Assert
        $this->expectException(LogicException::class, function () use ($resolver) {
            // Act

            $factory = new PQueueConsumerFactory($resolver, []); // This MUST throw LogicException
            $factory->createConsumer();
        });
    }

    private function testFactoryFailsIfHandlerIsNotInContainer()
    {
        // Arrange
        $container = $this->createMockContainer(false); // Container WITHOUT the handler
        $resolver = $this->createMockHandlerResolver($container);

        $sourceDir = $this->createTempDir();
        // Copy a handler that the container *doesn't* know about
        copy(__DIR__ . '/Extra/TestMessage.php', $sourceDir . '/TestMessage.php');
        copy(__DIR__ . '/Extra/TestMessageHandler.php', $sourceDir . '/TestMessageHandler.php');

        // Assert
        $this->expectException(LogicException::class, function () use ($resolver, $sourceDir) {
            // Act
            $finder  = new PQueueHandlerFinder([$sourceDir]);
            $factory = new PQueueConsumerFactory($resolver, $finder->find()); // This MUST throw LogicException because the handler is not in the container
            $factory->createConsumer();
        });
    }

    // --- Utility Helpers ---

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/' . uniqid('pqueue_consumer_factory_test_');
        mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($dir);
    }
}
