<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor;

use Ratib\ContactCenter\App\Core\Database;

final class SupervisorAlertRepository
{
    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_supervisor_alerts
             (tenant_id, alert_type, severity, title, title_ar, message, message_ar, source_event, queue_id, agent_id, payload_json)
             VALUES (:tid, :type, :sev, :title, :title_ar, :msg, :msg_ar, :src, :qid, :aid, :payload)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'type' => (string) ($data['alert_type'] ?? 'general'),
            'sev' => (string) ($data['severity'] ?? 'warning'),
            'title' => (string) ($data['title'] ?? 'Alert'),
            'title_ar' => $data['title_ar'] ?? null,
            'msg' => $data['message'] ?? null,
            'msg_ar' => $data['message_ar'] ?? null,
            'src' => $data['source_event'] ?? null,
            'qid' => $data['queue_id'] ?? null,
            'aid' => $data['agent_id'] ?? null,
            'payload' => isset($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function hasRecentOpenAlert(
        int $tenantId,
        string $alertType,
        ?int $queueId = null,
        ?int $agentId = null,
        int $withinMinutes = 15
    ): bool {
        $sql = 'SELECT 1 FROM rcc_supervisor_alerts
                WHERE tenant_id = :tid AND alert_type = :type AND acknowledged_at IS NULL
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :mins MINUTE)';
        $params = ['tid' => $tenantId, 'type' => $alertType, 'mins' => max(1, $withinMinutes)];
        if ($queueId !== null && $queueId > 0) {
            $sql .= ' AND queue_id = :qid';
            $params['qid'] = $queueId;
        }
        if ($agentId !== null && $agentId > 0) {
            $sql .= ' AND agent_id = :aid';
            $params['aid'] = $agentId;
        }
        $sql .= ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<string, mixed>|null */
    public function getRule(int $tenantId, string $ruleKey): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_supervisor_alert_rules WHERE tenant_id = :tid AND rule_key = :key LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'key' => $ruleKey]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, bool $openOnly = true, int $limit = 100): array
    {
        $sql = 'SELECT * FROM rcc_supervisor_alerts WHERE tenant_id = :tid';
        if ($openOnly) {
            $sql .= ' AND acknowledged_at IS NULL';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    public function acknowledge(int $tenantId, int $alertId, ?int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_supervisor_alerts SET acknowledged_by_user_id=:uid, acknowledged_at=NOW()
             WHERE id=:id AND tenant_id=:tid AND acknowledged_at IS NULL'
        );
        $stmt->execute(['uid' => $userId, 'id' => $alertId, 'tid' => $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function listRules(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_supervisor_alert_rules WHERE tenant_id = :tid ORDER BY rule_key'
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $config */
    public function saveRule(int $tenantId, string $ruleKey, bool $enabled, array $config = []): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_supervisor_alert_rules (tenant_id, rule_key, is_enabled, config_json)
             VALUES (:tid, :key, :en, :cfg)
             ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled), config_json=VALUES(config_json), updated_at=NOW()'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'key' => $ruleKey,
            'en' => $enabled ? 1 : 0,
            'cfg' => json_encode($config, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function isRuleEnabled(int $tenantId, string $ruleKey): bool
    {
        $row = $this->getRule($tenantId, $ruleKey);
        if ($row === null) {
            return true;
        }
        return (int) ($row['is_enabled'] ?? 1) === 1;
    }

    /** @return array<string, mixed> */
    public function ruleConfig(int $tenantId, string $ruleKey): array
    {
        $row = $this->getRule($tenantId, $ruleKey);
        if ($row === null || empty($row['config_json'])) {
            return [];
        }
        $decoded = json_decode((string) $row['config_json'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<array<string, mixed>> */
    public function openBreaksExceedingMinutes(int $tenantId, int $maxMinutes): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT b.*, a.display_name, a.extension,
                    TIMESTAMPDIFF(MINUTE, b.started_at, NOW()) AS break_minutes
             FROM rcc_wfm_breaks b
             INNER JOIN rcc_agents a ON a.id = b.agent_id AND a.tenant_id = b.tenant_id
             WHERE b.tenant_id = :tid AND b.ended_at IS NULL
               AND TIMESTAMPDIFF(MINUTE, b.started_at, NOW()) >= :mins'
        );
        $stmt->execute(['tid' => $tenantId, 'mins' => max(1, $maxMinutes)]);
        return $stmt->fetchAll() ?: [];
    }
}
