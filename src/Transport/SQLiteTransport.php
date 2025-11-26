<?php

namespace Depo\PQueue\Transport;

use DateTimeInterface;
use LogicException;
use Depo\PQueue\Transport\Message\Message;
use RuntimeException;
use SQLite3;

class SQLiteTransport implements TransportInterface
{
    private SQLite3 $db;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            throw new LogicException(sprintf(
                "The SQLite directory does not exist: %s",
                $dir
            ));
        }
        if (!file_exists($dbPath)) {
            $created = @touch($dbPath);
            if (!$created) {
                throw new RuntimeException(sprintf(
                    "Unable to create the SQLite database file: %s",
                    $dbPath
                ));
            }
        }

        $this->db = new SQLite3($dbPath);
        $this->initializeDatabase();
    }

    private function initializeDatabase(): void
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS pqueue_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                body TEXT NOT NULL,
                retry BOOLEAN NOT NULL,
                attempts INTEGER DEFAULT 0,
                available_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_failure_at DATETIME DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                status TEXT DEFAULT 'PENDING',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            SQL;

        if (!$this->db->exec($sql)) {
            throw new RuntimeException('Failed to create SQLite tables: ' . $this->db->lastErrorMsg());
        }
    }

    public function send(Envelope $message): void
    {
        $availableAtStr = $message->getAvailableAt() ? $message->getAvailableAt()->format('Y-m-d H:i:s') : date('Y-m-d H:i:00');
        $stmt = $this->db->prepare('INSERT INTO pqueue_messages (body, retry, available_at) VALUES (:body, :retry, :availableAt)');
        $stmt->bindValue(':body', $message->getBody(), SQLITE3_TEXT);
        $stmt->bindValue(':retry', $message->isRetry() ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':availableAt', $availableAtStr, SQLITE3_TEXT);
        $stmt->execute();
    }

    /**
     * @return iterable<Message>
     */
    public function getNextAvailableMessages(): iterable
    {
        $stmt = $this->db->prepare(
            <<<SQL
        SELECT id, body, retry, attempts, available_at as availableAt, last_failure_at as lastFailureAt, error_message as errorMessage, status  FROM pqueue_messages
        WHERE (status = 'PENDING' OR status = 'RETRY') AND available_at <= datetime('now')
        ORDER BY id ASC
        SQL
        );
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $envelope = Envelope::fromArray($row);
            yield new Message(
                $row['id'],
                $envelope
            );
        }
    }

    public function success(Message $message): void
    {
        $stmt = $this->db->prepare("DELETE FROM pqueue_messages WHERE id = :id");
        $stmt->bindValue(':id', $message->getId(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function retry(Message $message, string $errorMessage, DateTimeInterface $availableAt): void
    {
        $availableAtStr = $availableAt->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            <<<SQL
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

    public function failed(Message $message, string $errorMessage): void
    {
        $stmt = $this->db->prepare(
            <<<SQL
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

    public static function create(array $options): TransportInterface
    {
        if (!isset($options["db_path"])) {
            throw new \LogicException('The "db_path" option must be set');
        }

        if (!is_string($options["db_path"])) {
            throw new \LogicException('The "db_path" option must be a string');
        }

        return new SQLiteTransport($options["db_path"]);
    }
}
