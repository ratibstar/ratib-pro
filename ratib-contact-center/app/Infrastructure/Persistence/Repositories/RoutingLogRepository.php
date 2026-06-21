<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Core\Database;

final class RoutingLogRepository
{
    /** @param array<string, mixed> $decision */
    /** @param array<string, mixed> $scores */
    public function log(
        int $tenantId,
        int $callId,
        ?int $agentId,
        ?int $queueId,
        string $slaRisk,
        array $decision,
        array $scores
    ): int {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_routing_logs
             (tenant_id, call_id, selected_agent_id, selected_queue_id, sla_risk, decision_json, score_json)
             VALUES (:tid, :cid, :aid, :qid, :risk, :decision, :scores)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'cid' => $callId,
            'aid' => $agentId,
            'qid' => $queueId,
            'risk' => $slaRisk,
            'decision' => json_encode($decision, JSON_UNESCAPED_UNICODE),
            'scores' => json_encode($scores, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function countHistoricalBreaches(int $tenantId, int $queueId, int $days = 7): int
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rcc_routing_logs
                 WHERE tenant_id = :tid AND selected_queue_id = :qid
                   AND sla_risk = 'red'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)"
            );
            $stmt->bindValue('tid', $tenantId, \PDO::PARAM_INT);
            $stmt->bindValue('qid', $queueId, \PDO::PARAM_INT);
            $stmt->bindValue('days', $days, \PDO::PARAM_INT);
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function agentFamiliarityScore(int $tenantId, int $agentId, ?int $contactId): float
    {
        if ($contactId === null || $contactId < 1) {
            return 0.0;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rcc_routing_logs rl
                 INNER JOIN rcc_calls c ON c.id = rl.call_id AND c.tenant_id = rl.tenant_id
                 WHERE rl.tenant_id = :tid AND rl.selected_agent_id = :aid
                   AND c.caller_number IS NOT NULL
                   AND rl.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
            $count = (int) $stmt->fetchColumn();
            return min(1.0, $count / 10.0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}
