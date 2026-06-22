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
        $stmt = Database::connection()->prepare(
            'SELECT is_enabled FROM rcc_supervisor_alert_rules WHERE tenant_id=:tid AND rule_key=:key LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'key' => $ruleKey]);
        $val = $stmt->fetchColumn();
        return $val === false || (int) $val === 1;
    }
}
