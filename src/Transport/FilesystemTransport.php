<?php

namespace PhpDevCommunity\PQueue\Transport;

use DateTimeInterface;
use LogicException;
use PhpDevCommunity\PQueue\Transport\Message\TransportMessage;
use RuntimeException;
use SQLite3;

class FilesystemTransport implements TransportInterface
{
    const MESSAGE_EXTENSION = '.message';
    private string $directory;

    public function __construct(string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        $this->directory = rtrim($directory, '/') . '/';
    }


    public function send(string $body, ?DateTimeInterface $availableAt, bool $retry): void
    {
        $id = $this->generateUniqueId();
        $fileName = $this->generateFilenameById($id);

        $result = @file_put_contents($fileName, json_encode([
            'id' => $id,
        ]), LOCK_EX);
        if ($result === false) {
            throw new TransportException(sprintf('Could not write message to file "%s"', $fileName));
        }
    }

    /**
     * @return iterable<TransportMessage>
     */
    public function getNextAvailableMessages(): iterable
    {
        $stmt = $this->db->prepare(<<<SQL
        SELECT id, body, retry, attempts FROM pqueue_messages
        WHERE (status = 'PENDING' OR status = 'RETRY') AND available_at <= datetime('now')
        ORDER BY id ASC
        SQL
        );
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            yield new TransportMessage(
                $row['id'],
                $row['body'],
                (bool)$row['retry'],
                (int)$row['attempts']
            );
        }
    }

    public function success(TransportMessage $message): void
    {
        $stmt = $this->db->prepare("DELETE FROM pqueue_messages WHERE id = :id");
        $stmt->bindValue(':id', $message->getId(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function retry(TransportMessage $message, string $errorMessage, DateTimeInterface $availableAt): void
    {
        $availableAtStr = $availableAt->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare(<<<SQL
            UPDATE pqueue_messages
            SET attempts = attempts + 1,
                status = 'RETRY',
                available_at = :availableAt,
                error_message = :errorMessage,
                last_failure_at = datetime('now')
            WHERE id = :id
        SQL
        );
        $stmt->bindValue(':id', $message->getId(), SQLITE3_INTEGER);
        $stmt->bindValue(':availableAt', $availableAtStr, SQLITE3_TEXT);
        $stmt->bindValue(':errorMessage', $errorMessage, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function failed(TransportMessage $message, string $errorMessage): void
    {
        $stmt = $this->db->prepare(<<<SQL
            UPDATE pqueue_messages
            SET attempts = attempts + 1,
                status = 'FAILED',
                error_message = :errorMessage,
                last_failure_at = datetime('now')
            WHERE id = :id
        SQL
        );
        $stmt->bindValue(':id', $message->getId(), SQLITE3_INTEGER);
        $stmt->bindValue(':errorMessage', $errorMessage, SQLITE3_TEXT);
        $stmt->execute();
    }


    public function supportMultiWorker(): bool
    {
        return false;
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
}