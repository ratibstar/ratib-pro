<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\BaseModel;
use PDO;

final class AlertRepository extends BaseModel
{
    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $st = $this->db->query(
            "SELECT id, event_type, worker_id, workflow_id, severity, severity_score, message, dedupe_count, group_id, status, escalation_reason, created_at, last_seen_at
             FROM alert_events
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    }

    public function findRecentByDedupeHash(string $dedupeHash, int $windowSeconds): ?array
    {
        $st = $this->db->prepare(
            "SELECT *
             FROM alert_events
             WHERE dedupe_hash = :dedupe_hash
               AND last_seen_at >= DATE_SUB(NOW(), INTERVAL :window_sec SECOND)
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->bindValue(':dedupe_hash', $dedupeHash, PDO::PARAM_STR);
        $st->bindValue(':window_sec', max(1, $windowSeconds), PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();

        return is_array($row) ? $row : null;
    }

    public function touchDuplicate(int $alertId): void
    {
        $st = $this->db->prepare(
            "UPDATE alert_events
             SET dedupe_count = dedupe_count + 1,
                 last_seen_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $st->execute([':id' => $alertId]);
    }

    public function findOpenGroup(string $groupKey, int $windowMinutes): ?array
    {
        $st = $this->db->prepare(
            "SELECT *
             FROM alert_groups
             WHERE group_key = :group_key
               AND status = 'open'
               AND last_alert_at >= DATE_SUB(NOW(), INTERVAL :window_min MINUTE)
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->bindValue(':group_key', $groupKey, PDO::PARAM_STR);
        $st->bindValue(':window_min', max(1, $windowMinutes), PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();

        return is_array($row) ? $row : null;
    }

    public function createGroup(string $groupKey, string $severity): int
    {
        $st = $this->db->prepare(
            "INSERT INTO alert_groups (group_key, severity, status, alerts_count, first_alert_at, last_alert_at, created_at, updated_at)
             VALUES (:group_key, :severity, 'open', 1, NOW(), NOW(), NOW(), NOW())"
        );
        $st->execute([
            ':group_key' => $groupKey,
            ':severity' => $severity,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function incrementGroup(int $groupId): void
    {
        $st = $this->db->prepare(
            "UPDATE alert_groups
             SET alerts_count = alerts_count + 1,
                 last_alert_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $st->execute([':id' => $groupId]);
    }

    public function createAlert(array $payload): int
    {
        $st = $this->db->prepare(
            "INSERT INTO alert_events
             (event_type, worker_id, workflow_id, severity, severity_score, message, dedupe_hash, dedupe_count, group_key, group_id, status, created_at, last_seen_at, updated_at)
             VALUES
             (:event_type, :worker_id, :workflow_id, :severity, :severity_score, :message, :dedupe_hash, 1, :group_key, :group_id, :status, NOW(), NOW(), NOW())"
        );
        $st->bindValue(':event_type', (string) ($payload['event_type'] ?? ''), PDO::PARAM_STR);
        $wid = (int) ($payload['worker_id'] ?? 0);
        $st->bindValue(':worker_id', $wid > 0 ? $wid : null, $wid > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $wfid = (int) ($payload['workflow_id'] ?? 0);
        $st->bindValue(':workflow_id', $wfid > 0 ? $wfid : null, $wfid > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':severity', (string) ($payload['severity'] ?? 'low'), PDO::PARAM_STR);
        $st->bindValue(':severity_score', (int) ($payload['severity_score'] ?? 0), PDO::PARAM_INT);
        $st->bindValue(':message', (string) ($payload['message'] ?? ''), PDO::PARAM_STR);
        $st->bindValue(':dedupe_hash', (string) ($payload['dedupe_hash'] ?? ''), PDO::PARAM_STR);
        $st->bindValue(':group_key', (string) ($payload['group_key'] ?? ''), PDO::PARAM_STR);
        $gid = (int) ($payload['group_id'] ?? 0);
        $st->bindValue(':group_id', $gid > 0 ? $gid : null, $gid > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':status', (string) ($payload['status'] ?? 'open'), PDO::PARAM_STR);
        $st->execute();

        return (int) $this->db->lastInsertId();
    }

    public function escalateAlert(int $alertId, string $reason): void
    {
        $st = $this->db->prepare(
            "UPDATE alert_events
             SET status = 'escalated',
                 escalation_reason = :reason,
                 escalated_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $st->execute([':id' => $alertId, ':reason' => $reason]);
    }

    public function closeGroupAsEscalated(int $groupId): void
    {
        $st = $this->db->prepare(
            "UPDATE alert_groups
             SET status = 'escalated',
                 escalated_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $st->execute([':id' => $groupId]);
    }
}
