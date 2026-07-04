<?php
declare(strict_types=1);

namespace App\Accounting\Core;

use App\Accounting\Infrastructure\AccountingConnectionFactory;

/**
 * Ensures the same event_uuid is never routed to adapters twice.
 */
final class AccountingIdempotency
{
    /** @var array<string, bool> */
    private static array $memoryCache = [];

    public function __construct(
        private readonly ?\PDO $pdo = null,
    ) {
    }

    private function connection(): ?\PDO
    {
        return $this->pdo ?? AccountingConnectionFactory::pdo();
    }

    public function tableExists(): bool
    {
        $pdo = $this->connection();
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'accounting_processed_events'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function wasProcessed(string $eventUuid): bool
    {
        if ($eventUuid === '') {
            return false;
        }

        if (isset(self::$memoryCache[$eventUuid])) {
            return true;
        }

        $pdo = $this->connection();
        if ($pdo === null || !$this->tableExists()) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM accounting_processed_events WHERE event_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $eventUuid]);
        $found = (bool) $stmt->fetchColumn();

        if ($found) {
            self::$memoryCache[$eventUuid] = true;
        }

        return $found;
    }

    /**
     * @param array<string, mixed> $resultPayload
     */
    public function markProcessed(string $eventUuid, string $sourceSystem, array $resultPayload = []): bool
    {
        if ($eventUuid === '') {
            return false;
        }

        self::$memoryCache[$eventUuid] = true;

        $pdo = $this->connection();
        if ($pdo === null || !$this->tableExists()) {
            return true;
        }

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO accounting_processed_events
            (event_uuid, source_system, result_status, result_payload, processed_at)
            VALUES (:uuid, :source, :status, :payload, NOW())'
        );

        return $stmt->execute([
            'uuid' => $eventUuid,
            'source' => $sourceSystem,
            'status' => 'processed',
            'payload' => json_encode($resultPayload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function clear(string $eventUuid): bool
    {
        unset(self::$memoryCache[$eventUuid]);

        $pdo = $this->connection();
        if ($pdo === null || !$this->tableExists()) {
            return true;
        }

        $stmt = $pdo->prepare('DELETE FROM accounting_processed_events WHERE event_uuid = :uuid');

        return $stmt->execute(['uuid' => $eventUuid]);
    }
}
