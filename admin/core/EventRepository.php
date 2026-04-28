<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/EventRepository.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/EventRepository.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class EventRepository
{
    /**
     * @param array<string, mixed> $event
     */
    public static function insert(PDO $pdo, array $event): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO system_events (event_type, level, tenant_id, user_id, request_id, source, message, metadata, created_at)
             VALUES (:event_type, :level, :tenant_id, :user_id, :request_id, :source, :message, :metadata, NOW())'
        );
        $stmt->bindValue(':event_type', substr((string) ($event['event_type'] ?? 'UNKNOWN_EVENT'), 0, 100));
        $stmt->bindValue(':level', (string) ($event['level'] ?? 'info'));
        $tenantId = isset($event['tenant_id']) ? (int) $event['tenant_id'] : null;
        $userId = isset($event['user_id']) ? (int) $event['user_id'] : null;
        $stmt->bindValue(':tenant_id', $tenantId, $tenantId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':request_id', substr((string) ($event['request_id'] ?? ''), 0, 32));
        $stmt->bindValue(':source', substr((string) ($event['source'] ?? 'control_center'), 0, 50));
        $stmt->bindValue(':message', (string) ($event['message'] ?? ''));
        $stmt->bindValue(':metadata', isset($event['metadata']) ? (string) $event['metadata'] : null);
        $stmt->execute();
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    public static function insertBatch(PDO $pdo, array $events): int
    {
        if ($events === []) {
            return 0;
        }
        $chunks = [];
        $bind = [];
        $i = 0;
        foreach ($events as $ev) {
            $chunks[] = "(:event_type_{$i}, :level_{$i}, :tenant_id_{$i}, :user_id_{$i}, :request_id_{$i}, :source_{$i}, :message_{$i}, :metadata_{$i}, NOW())";
            $bind[":event_type_{$i}"] = substr((string) ($ev['event_type'] ?? 'UNKNOWN_EVENT'), 0, 100);
            $bind[":level_{$i}"] = (string) ($ev['level'] ?? 'info');
            $bind[":tenant_id_{$i}"] = isset($ev['tenant_id']) ? (int) $ev['tenant_id'] : null;
            $bind[":user_id_{$i}"] = isset($ev['user_id']) ? (int) $ev['user_id'] : null;
            $bind[":request_id_{$i}"] = substr((string) ($ev['request_id'] ?? ''), 0, 32);
            $bind[":source_{$i}"] = substr((string) ($ev['source'] ?? 'control_center'), 0, 50);
            $bind[":message_{$i}"] = (string) ($ev['message'] ?? '');
            $bind[":metadata_{$i}"] = isset($ev['metadata']) ? (string) $ev['metadata'] : null;
            $i++;
        }
        $sql = 'INSERT INTO system_events (event_type, level, tenant_id, user_id, request_id, source, message, metadata, created_at) VALUES '
            . implode(', ', $chunks);
        $stmt = $pdo->prepare($sql);
        foreach ($bind as $k => $v) {
            if (str_starts_with($k, ':tenant_id_') || str_starts_with($k, ':user_id_')) {
                $stmt->bindValue($k, $v, $v === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();
        return count($events);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function latest(array $filters = [], int $limit = 100): array
    {
        $db = getControlDB();
        $limit = max(1, min(500, $limit));

        $sql = 'SELECT * FROM system_events WHERE 1=1';
        $params = [];

        if (!empty($filters['tenant_id']) && (int) $filters['tenant_id'] > 0) {
            $sql .= ' AND tenant_id = :tenant_id';
            $params[':tenant_id'] = (int) $filters['tenant_id'];
        }
        if (!empty($filters['level'])) {
            $sql .= ' AND level = :level';
            $params[':level'] = (string) $filters['level'];
        }
        if (!empty($filters['event_type'])) {
            $sql .= ' AND event_type = :event_type';
            $params[':event_type'] = (string) $filters['event_type'];
        }
        if (!empty($filters['request_id'])) {
            $sql .= ' AND request_id = :request_id';
            $params[':request_id'] = substr((string) $filters['request_id'], 0, 32);
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND created_at >= :from';
            $params[':from'] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND created_at <= :to';
            $params[':to'] = (string) $filters['to'];
        }
        if (!empty($filters['since_id']) && (int) $filters['since_id'] > 0) {
            $sql .= ' AND id > :since_id';
            $params[':since_id'] = (int) $filters['since_id'];
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            if ($k === ':tenant_id' || $k === ':since_id') {
                $stmt->bindValue($k, (int) $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Events with id greater than $afterId, ascending (for SSE / streaming).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function streamAfterId(int $afterId, int $limit = 50): array
    {
        $db = getControlDB();
        $limit = max(1, min(200, $limit));
        $stmt = $db->prepare(
            'SELECT * FROM system_events WHERE id > :after ORDER BY id ASC LIMIT :lim'
        );
        $stmt->bindValue(':after', max(0, $afterId), PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countByEventTypeSince(PDO $pdo, string $eventType, int $seconds): int
    {
        $seconds = max(10, min(3600, $seconds));
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c FROM system_events
             WHERE event_type = :t AND created_at > (NOW() - INTERVAL ' . (int) $seconds . ' SECOND)'
        );
        $stmt->execute([':t' => substr($eventType, 0, 100)]);
        return (int) (($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
    }

    /**
     * @param string[] $levels
     */
    public static function countByTypeAndLevelsSince(PDO $pdo, string $eventType, array $levels, int $seconds): int
    {
        $seconds = max(10, min(3600, $seconds));
        $levels = array_values(array_unique(array_filter(array_map('strtolower', $levels))));
        if ($levels === []) {
            return 0;
        }
        $placeholders = [];
        $bind = [substr($eventType, 0, 100)];
        foreach ($levels as $lv) {
            $placeholders[] = '?';
            $bind[] = $lv;
        }
        $in = implode(',', $placeholders);
        $sql = "SELECT COUNT(*) AS c FROM system_events
                WHERE event_type = ?
                  AND created_at > (NOW() - INTERVAL {$seconds} SECOND)
                  AND level IN ({$in})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        return (int) (($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
    }

    public static function countQueryExecutedSince(PDO $pdo, int $seconds): int
    {
        $seconds = max(10, min(3600, $seconds));
        $stmt = $pdo->query(
            "SELECT COUNT(*) AS c FROM system_events
             WHERE event_type = 'QUERY_EXECUTED'
               AND created_at > (NOW() - INTERVAL {$seconds} SECOND)"
        );
        return (int) (($stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) : 0));
    }

    public static function countByTenantSince(PDO $pdo, int $tenantId, int $seconds): int
    {
        if ($tenantId <= 0) {
            return 0;
        }
        $seconds = max(10, min(3600, $seconds));
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c FROM system_events
             WHERE tenant_id = :tid AND created_at > (NOW() - INTERVAL ' . (int) $seconds . ' SECOND)'
        );
        $stmt->execute([':tid' => $tenantId]);
        return (int) (($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
    }

    public static function countErrorsSince(PDO $pdo, int $seconds): int
    {
        $seconds = max(10, min(3600, $seconds));
        $stmt = $pdo->query(
            "SELECT COUNT(*) AS c FROM system_events
             WHERE level IN ('error','critical')
               AND created_at > (NOW() - INTERVAL {$seconds} SECOND)"
        );
        return (int) (($stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) : 0));
    }

    public static function countEventsSince(PDO $pdo, int $seconds): int
    {
        $seconds = max(10, min(3600, $seconds));
        $stmt = $pdo->query(
            "SELECT COUNT(*) AS c FROM system_events
             WHERE created_at > (NOW() - INTERVAL {$seconds} SECOND)"
        );
        return (int) (($stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) : 0));
    }

    public static function countDistinctTenantsSince(PDO $pdo, int $seconds): int
    {
        $seconds = max(10, min(3600, $seconds));
        $stmt = $pdo->query(
            "SELECT COUNT(DISTINCT tenant_id) AS c FROM system_events
             WHERE tenant_id IS NOT NULL AND tenant_id > 0
               AND created_at > (NOW() - INTERVAL {$seconds} SECOND)"
        );
        return (int) (($stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) : 0));
    }

    public static function purgeOlderThanDays(PDO $pdo, int $days): int
    {
        $days = max(1, min(3650, $days));
        $stmt = $pdo->exec(
            "DELETE FROM system_events WHERE created_at < (NOW() - INTERVAL {$days} DAY)"
        );
        return is_int($stmt) ? $stmt : 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function trace(string $requestId): array
    {
        $db = getControlDB();
        $rid = substr(trim($requestId), 0, 32);
        if ($rid === '') {
            return [];
        }
        $stmt = $db->prepare(
            'SELECT * FROM system_events
             WHERE request_id = :rid
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([':rid' => $rid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
