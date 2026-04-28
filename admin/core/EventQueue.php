<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/EventQueue.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/EventQueue.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class EventQueue
{
    public const BACKPRESSURE_LIMIT = 10000;
    private const FALLBACK_FILE = __DIR__ . '/../storage/event-queue-fallback.ndjson';

    public static function ensureTable(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS event_queue (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type VARCHAR(100) NOT NULL,
                level ENUM('info','warn','error','critical') NOT NULL DEFAULT 'info',
                priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
                tenant_id BIGINT NULL,
                user_id BIGINT NULL,
                request_id VARCHAR(32) NOT NULL,
                source VARCHAR(50) NOT NULL DEFAULT 'control_center',
                message TEXT NOT NULL,
                metadata LONGTEXT NULL,
                attempts INT NOT NULL DEFAULT 0,
                available_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_event_queue_available (available_at, id),
                KEY idx_event_queue_priority (priority),
                KEY idx_event_queue_request (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ready = true;
    }

    public static function queueSize(PDO $pdo): int
    {
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM event_queue');
        return (int) (($stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) : 0));
    }

    public static function normalizePriority(string $level, ?string $priority): string
    {
        $p = strtolower(trim((string) ($priority ?? '')));
        if (in_array($p, ['low', 'normal', 'high', 'critical'], true)) {
            return $p;
        }
        switch (strtolower(trim($level))) {
            case 'critical':
                return 'critical';
            case 'error':
                return 'high';
            case 'warn':
                return 'normal';
            default:
                return 'low';
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function push(PDO $pdo, array $event): bool
    {
        self::ensureTable($pdo);
        $priority = self::normalizePriority((string) ($event['level'] ?? 'info'), isset($event['priority']) ? (string) $event['priority'] : null);

        $size = self::queueSize($pdo);
        if ($size > self::BACKPRESSURE_LIMIT && $priority === 'low') {
            return false;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO event_queue (event_type, level, priority, tenant_id, user_id, request_id, source, message, metadata, attempts, available_at, created_at)
             VALUES (:event_type, :level, :priority, :tenant_id, :user_id, :request_id, :source, :message, :metadata, 0, NOW(), NOW())'
        );
        $stmt->bindValue(':event_type', substr((string) ($event['event_type'] ?? 'UNKNOWN_EVENT'), 0, 100));
        $stmt->bindValue(':level', (string) ($event['level'] ?? 'info'));
        $stmt->bindValue(':priority', $priority);
        $tenantId = isset($event['tenant_id']) ? (int) $event['tenant_id'] : null;
        $userId = isset($event['user_id']) ? (int) $event['user_id'] : null;
        $stmt->bindValue(':tenant_id', $tenantId, $tenantId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':request_id', substr((string) ($event['request_id'] ?? ''), 0, 32));
        $stmt->bindValue(':source', substr((string) ($event['source'] ?? 'control_center'), 0, 50));
        $stmt->bindValue(':message', (string) ($event['message'] ?? ''));
        $stmt->bindValue(':metadata', isset($event['metadata']) ? (string) $event['metadata'] : null);
        return $stmt->execute();
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function pushWithCircuitBreaker(PDO $pdo, array $event): bool
    {
        try {
            return self::push($pdo, $event);
        } catch (Throwable $e) {
            self::appendFallback($event);
            return false;
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function appendFallback(array $event): void
    {
        $dir = dirname(self::FALLBACK_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        @file_put_contents(self::FALLBACK_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function importFallback(PDO $pdo, int $max = 500): int
    {
        if (!is_file(self::FALLBACK_FILE)) {
            return 0;
        }
        $max = max(1, min(5000, $max));
        $fp = @fopen(self::FALLBACK_FILE, 'c+');
        if ($fp === false) {
            return 0;
        }
        $imported = 0;
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return 0;
        }
        $content = stream_get_contents($fp);
        $lines = $content !== false ? preg_split('/\r?\n/', trim($content)) : [];
        $lines = is_array($lines) ? $lines : [];
        $remaining = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            if ($imported >= $max) {
                $remaining[] = $line;
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            try {
                self::push($pdo, $decoded);
                $imported++;
            } catch (Throwable $e) {
                $remaining[] = $line;
            }
        }
        ftruncate($fp, 0);
        rewind($fp);
        if ($remaining !== []) {
            fwrite($fp, implode(PHP_EOL, $remaining) . PHP_EOL);
        }
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $imported;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function popBatch(PDO $pdo, int $limit = 100): array
    {
        self::ensureTable($pdo);
        $limit = max(1, min(200, $limit));
        $pdo->beginTransaction();
        try {
            try {
                $stmt = $pdo->query(
                    "SELECT * FROM event_queue
                     WHERE available_at <= NOW()
                     ORDER BY FIELD(priority, 'critical','high','normal','low'), id ASC
                     LIMIT {$limit}
                     FOR UPDATE SKIP LOCKED"
                );
            } catch (Throwable $e) {
                $stmt = $pdo->query(
                    "SELECT * FROM event_queue
                     WHERE available_at <= NOW()
                     ORDER BY FIELD(priority, 'critical','high','normal','low'), id ASC
                     LIMIT {$limit}
                     FOR UPDATE"
                );
            }
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            if ($rows === []) {
                $pdo->commit();
                return [];
            }
            $ids = array_map(static fn($r) => (int) ($r['id'] ?? 0), $rows);
            $in = implode(',', array_map('intval', $ids));
            $pdo->exec("DELETE FROM event_queue WHERE id IN ({$in})");
            $pdo->commit();
            return $rows;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public static function requeueBatch(PDO $pdo, array $rows, int $delaySeconds = 5): void
    {
        self::ensureTable($pdo);
        $delaySeconds = max(1, min(300, $delaySeconds));
        $stmt = $pdo->prepare(
            'INSERT INTO event_queue (event_type, level, priority, tenant_id, user_id, request_id, source, message, metadata, attempts, available_at, created_at)
             VALUES (:event_type, :level, :priority, :tenant_id, :user_id, :request_id, :source, :message, :metadata, :attempts, DATE_ADD(NOW(), INTERVAL :delay SECOND), NOW())'
        );
        foreach ($rows as $r) {
            $attempts = (int) ($r['attempts'] ?? 0) + 1;
            if ($attempts > 10) {
                continue;
            }
            $stmt->bindValue(':event_type', substr((string) ($r['event_type'] ?? 'UNKNOWN_EVENT'), 0, 100));
            $stmt->bindValue(':level', (string) ($r['level'] ?? 'info'));
            $stmt->bindValue(':priority', self::normalizePriority((string) ($r['level'] ?? 'info'), isset($r['priority']) ? (string) $r['priority'] : null));
            $tenantId = isset($r['tenant_id']) ? (int) $r['tenant_id'] : null;
            $userId = isset($r['user_id']) ? (int) $r['user_id'] : null;
            $stmt->bindValue(':tenant_id', $tenantId, $tenantId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':request_id', substr((string) ($r['request_id'] ?? ''), 0, 32));
            $stmt->bindValue(':source', substr((string) ($r['source'] ?? 'control_center'), 0, 50));
            $stmt->bindValue(':message', (string) ($r['message'] ?? ''));
            $stmt->bindValue(':metadata', isset($r['metadata']) ? (string) $r['metadata'] : null);
            $stmt->bindValue(':attempts', $attempts, PDO::PARAM_INT);
            $stmt->bindValue(':delay', $delaySeconds, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
}

