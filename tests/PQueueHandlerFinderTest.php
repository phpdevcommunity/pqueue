<?php

namespace Test\Depo\PQueue;

use Depo\UniTester\TestCase;
use Depo\PQueue\PQueueHandlerFinder;
use LogicException;
use InvalidArgumentException;
use Test\Depo\PQueue\Extra\TestMessage;
use Test\Depo\PQueue\Extra\TestMessageHandler;

class PQueueHandlerFinderTest extends TestCase
{
    private array $tempDirs = [];

    protected function setUp(): void
    {
        // Each test is self-contained.
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

    public function execute(): void
    {
        $this->testFindsSingleHandlerCorrectly();
        $this->testFailsWhenMultipleHandlersExist();
        $this->testCacheWorksCorrectly();
        $this->testFailsForInvalidDirectory();
    }

    /**
     * Test: The finder correctly finds a single, valid handler.
     */
    private function testFindsSingleHandlerCorrectly()
    {
        // Given: A temporary directory with ONE valid handler copied from the Extra directory.
        $sourceDir = $this->createTempDir();
        copy(__DIR__ . '/Extra/TestMessage.php', $sourceDir . '/TestMessage.php');
        copy(__DIR__ . '/Extra/TestMessageHandler.php', $sourceDir . '/TestMessageHandler.php');

        // When: We run the finder on that directory.
        $finder = new PQueueHandlerFinder([$sourceDir]);
        $handlerMap = $finder->find();

        // Then: The map should contain exactly one correct entry.
        $this->assertArrayHasKey(TestMessage::class, $handlerMap);
        $this->assertStrictEquals(TestMessageHandler::class, $handlerMap[TestMessage::class]);
        $this->assertCount(1, $handlerMap);
    }

    /**
     * Test: The finder throws an exception when scanning the Extra directory, which has multiple handlers.
     */
    private function testFailsWhenMultipleHandlersExist()
    {
        $this->expectException(LogicException::class, function () {
            // When: We run the finder on that directory.
            $finder = new PQueueHandlerFinder([__DIR__ . '/Extra']);
            $finder->find();
        });
    }

    /**
     * Test: The finder creates and uses a cache file correctly.
     */
    private function testCacheWorksCorrectly()
    {
        // Given: A temporary directory with one handler and a cache directory.
        $sourceDir = $this->createTempDir();
        $cacheDir = $this->createTempDir();
        $handlerFile = $sourceDir . '/TestMessageHandler.php';
        copy(__DIR__ . '/Extra/TestMessage.php', $sourceDir . '/TestMessage.php');
        copy(__DIR__ . '/Extra/TestMessageHandler.php', $handlerFile);

        // When: We run the finder the first time.
        $finder = new PQueueHandlerFinder([$sourceDir], $cacheDir);
        $handlerMap1 = $finder->find();

        // Then: The cache file must exist.
        $this->assertFileExists($cacheDir . '/pqueue_handler_map.php');

        // And When: We delete the source handler file and run the finder again.
        unlink($handlerFile);
        $finder2 = new PQueueHandlerFinder([$sourceDir], $cacheDir);
        $handlerMap2 = $finder2->find();

        // Then: The result should be identical because it was loaded from cache.
        $this->assertNotEmpty($handlerMap2, 'Cache should not be empty');
        $this->assertStrictEquals($handlerMap1, $handlerMap2, 'Result from cache should match the original');
    }

    /**
     * Test: The finder fails if the source directory does not exist.
     */
    private function testFailsForInvalidDirectory()
    {
        $this->expectException(InvalidArgumentException::class, function () {
            new PQueueHandlerFinder(['/this/directory/does/not/exist']);
        });
    }

    // --- UTILITY HELPERS ---

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/' . uniqid('pqueue_test_');
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
