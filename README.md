# PQueue - Simple PHP Queue Library

PQueue is a lightweight, framework-agnostic library for handling background jobs and messages with persistent queues.

## Features

-   **Multiple Transports**: Comes with `SQLite` and `Filesystem` transports.
-   **DI-Friendly**: Designed to integrate cleanly with any PSR-11 dependency injection container.
-   **Configurable Worker**: The queue worker can be configured with memory limits, time limits, retry strategies, and more.
-   **Automatic Handler Discovery**: Scans specified directories to find your message handlers automatically.

## Installation

```bash
composer require depo/pqueue
```

## Basic Usage (Without a Framework)

This example shows how to use the library in a simple PHP script.

**1. Create a Message and a Handler**

```php
// src/Messages/MyMessage.php
namespace App\Messages;
class MyMessage {
    public string $text;
    public function __construct(string $text) { $this->text = $text; }
}

// src/Handlers/MyMessageHandler.php
namespace App\Handlers;
use App\Messages\MyMessage;
class MyMessageHandler {
    public function __invoke(MyMessage $message) {
        echo "Processing message: " . $message->text . "\n";
    }
}
```

**2. Dispatch a Message**

```php
// send_message.php
require 'vendor/autoload.php';

use PhpDevCommunity\PQueue\Transport\SQLiteTransport;
use PhpDevCommunity\PQueue\PQueueDispatcher;
use App\Messages\MyMessage;

// 1. Create a transport
$transport = SQLiteTransport::create(['db_path' => __DIR__ . '/pqueue.sqlite']);

// 2. Create a dispatcher
$dispatcher = new PQueueDispatcher($transport);

// 3. Dispatch your message
$dispatcher->dispatch(new MyMessage('Hello, World!'));

echo "Message dispatched!\n";
```

**3. Run the Worker**

The worker needs a `HandlerResolver` to get handler instances. For this simple example, we'll create a basic one.

```php
// worker.php
require 'vendor/autoload.php';

use PhpDevCommunity\PQueue\PQueueConsumerFactory;
use PhpDevCommunity\PQueue\PQueueWorker;
use PhpDevCommunity\PQueue\HandlerResolver\HandlerResolverInterface;
use PhpDevCommunity\PQueue\Transport\SQLiteTransport;
use App\Handlers\MyMessageHandler; // Import the handler class

// 1. Create a simple handler resolver for the example
$handlerResolver = new class implements HandlerResolverInterface {
    private array $handlers = [];
    public function getHandler(string $className): object {
        if (!isset($this->handlers[$className])) {
            $this->handlers[$className] = new $className();
        }
        return $this->handlers[$className];
    }
    public function hasHandler(string $className): bool {
        return class_exists($className);
    }
};

// 2. Create the transport
$transport = SQLiteTransport::create(['db_path' => __DIR__ . '/pqueue.sqlite']);

// 3. Use the factory to build the consumer
$factory = new PQueueConsumerFactory(
    $handlerResolver,
    [
        MyMessageHandler::class,  // You can add handler classes directly
        __DIR__ . '/src/Handlers' // And also scan directories
    ], 
    __DIR__ . '/cache'            // Cache directory for handler discovery
);
$consumer = $factory->createConsumer();

// 4. Create and run the worker
$worker = new PQueueWorker($transport, $consumer, [
    'stopWhenEmpty' => true, // Stop after processing all messages
]);
$worker->run();

echo "Worker finished.\n";
```

## Worker Callbacks

You can hook into the worker lifecycle using the following methods:

- `onConsume(callable $callback)`: Executed after a message is successfully consumed.
- `onFailure(callable $callback)`: Executed when a message fails processing.
- `onStop(callable $callback)`: Executed when the worker stops (due to memory limit, time limit, or empty queue).

```php
$worker->onConsume(function ($message) {
    echo "Message processed!\n";
});

$worker->onFailure(function ($message, $exception) {
    echo "Message failed: " . $exception->getMessage() . "\n";
});

$worker->onStop(function () {
    echo "Worker stopped.\n";
});
```
