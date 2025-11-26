<?php

namespace Depo\PQueue\Transport;

use DateTimeInterface;
use LogicException;
use Depo\PQueue\Transport\Message\Message;
use RuntimeException;
use SQLite3;

class FilesystemTransport implements TransportInterface
{
    const MESSAGE_EXTENSION = '.message';
    const FAILED_EXTENSION = '.failed';

    private string $directory;

    public function __construct(string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        $this->directory = rtrim($directory, '/') . '/';
    }


    public function send(Envelope $message): void
    {
        $id = $this->generateUniqueId();
        $this->write($id, $message);
    }

    public function getNextAvailableMessages(): iterable
    {
        $files = glob($this->directory . '*' . self::MESSAGE_EXTENSION);
        usort($files, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            $envelope = Envelope::fromArray($data);

            if ($envelope->getAvailableAt() && $envelope->getAvailableAt()->getTimestamp() > time()) {
                continue;
            }

            yield new Message(
                $data['id'],
                $envelope
            );
        }
    }

    public function success(Message $message): void
    {
        $filename = $this->generateFilenameById($message->getId());
        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    public function retry(Message $message, string $errorMessage, DateTimeInterface $availableAt): void
    {
        $envelope = $this->read($message->getId());

        $newEnvelope = new Envelope(
            $envelope->getBody(),
            $envelope->isRetry(),
            $envelope->getAttempts() + 1,
            $availableAt,
            new \DateTimeImmutable(),
            $errorMessage
        );

        $this->write($message->getId(), $newEnvelope);
    }

    public function failed(Message $message, string $errorMessage): void
    {
        $envelope = $this->read($message->getId());

        $newEnvelope = new Envelope(
            $envelope->getBody(),
            $envelope->isRetry(),
            $envelope->getAttempts() + 1,
            $envelope->getAvailableAt(),
            new \DateTimeImmutable(),
            $errorMessage
        );

        $this->write($message->getId(), $newEnvelope);

        $filename = $this->generateFilenameById($message->getId());
        rename($filename, $filename . self::FAILED_EXTENSION);
    }

    public function supportMultiWorker(): bool
    {
        return false;
    }

    private function read(string $id): Envelope
    {
        $filename = $this->generateFilenameById($id);
        if (!file_exists($filename)) {
            throw new RuntimeException(sprintf('Message file "%s" does not exist for id "%s"', $filename, $id));
        }

        $content = file_get_contents($filename);
        $data = json_decode($content, true);
        return Envelope::fromArray($data);
    }

    private function write(string $id, Envelope $envelope): void
    {
        $filename = $this->generateFilenameById($id);
        $data = array_merge(['id' => $id], $envelope->toArray());
        $result = @file_put_contents($filename, json_encode($data), LOCK_EX);
        if ($result === false) {
            throw new RuntimeException(sprintf('Could not write message to file "%s"', $filename));
        }
    }

    private function generateUniqueId(): string
    {
        do {
            $id = uniqid(date("Ymd_His_") . gettimeofday()["usec"]);
            $fileName = $this->generateFilenameById($id);
        } while (file_exists($fileName));

        return $id;
    }

    private function generateFilenameById(string $id): string
    {
        return sprintf("%s%s%s", $this->directory, $id, self::MESSAGE_EXTENSION);
    }

    public static function create(array $options): TransportInterface
    {
        if (!isset($options["directory"])) {
            throw new \LogicException('The "directory" option must be set');
        }

        if (!is_string($options["directory"])) {
            throw new \LogicException('The "directory" option must be a string');
        }

        return new FilesystemTransport($options["directory"]);
    }
}
