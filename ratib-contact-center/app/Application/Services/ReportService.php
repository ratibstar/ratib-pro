<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Core\Database;

/**
 * Production reporting — agent, queue, SLA, calls, conversations, AI.
 */
final class ReportService
{
    /** @return list<array<string, mixed>> */
    public function agentPerformance(int $tenantId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.id AS agent_id, a.display_name, a.extension,
                    COUNT(DISTINCT sc.id) AS total_calls,
                    SUM(CASE WHEN sc.status = 'ended' THEN COALESCE(sc.duration_seconds,0) ELSE 0 END) AS talk_seconds,
                    AVG(CASE WHEN sc.status = 'ended' THEN COALESCE(sc.duration_seconds,0) END) AS avg_talk_seconds
             FROM rcc_agents a
             LEFT JOIN rcc_softphone_calls sc ON sc.agent_id = a.id AND sc.tenant_id = a.tenant_id
               AND sc.started_at BETWEEN :from AND :to
             WHERE a.tenant_id = :tid AND a.status = 'active'
             GROUP BY a.id, a.display_name, a.extension
             ORDER BY total_calls DESC"
        );
        $stmt->execute(['tid' => $tenantId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function queuePerformance(int $tenantId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT q.id AS queue_id, q.code, q.name,
                    COUNT(c.id) AS calls_queued,
                    AVG(TIMESTAMPDIFF(SECOND, c.started_at, COALESCE(c.connected_at, c.ended_at))) AS avg_wait_seconds
             FROM rcc_queues q
             LEFT JOIN rcc_calls c ON c.queue_id = q.id AND c.tenant_id = q.tenant_id
               AND c.started_at BETWEEN :from AND :to
             WHERE q.tenant_id = :tid
             GROUP BY q.id, q.code, q.name"
        );
        $stmt->execute(['tid' => $tenantId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function slaReport(int $tenantId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT selected_queue_id AS queue_id, sla_risk, COUNT(*) AS decisions
             FROM rcc_routing_logs
             WHERE tenant_id = :tid AND created_at BETWEEN :from AND :to
             GROUP BY selected_queue_id, sla_risk"
        );
        $stmt->execute(['tid' => $tenantId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function callReport(int $tenantId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, uuid, direction, status, caller_number, agent_id, queue_id,
                    started_at, connected_at, ended_at
             FROM rcc_calls
             WHERE tenant_id = :tid AND started_at BETWEEN :from AND :to
             ORDER BY started_at DESC LIMIT 5000"
        );
        $stmt->execute(['tid' => $tenantId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function conversationReport(int $tenantId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, customer_identity, status, priority, sla_status, assigned_agent_id,
                    last_channel, last_message_at
             FROM rcc_conversations
             WHERE tenant_id = :tid AND created_at BETWEEN :from AND :to
             ORDER BY last_message_at DESC LIMIT 5000"
        );
        $stmt->execute(['tid' => $tenantId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function aiReport(int $tenantId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT conversation_id, sentiment, intent, risk_score, recommended_action, ticket_id, updated_at
             FROM rcc_ai_context
             WHERE tenant_id = :tid AND updated_at BETWEEN :from AND :to
             ORDER BY updated_at DESC LIMIT 2000"
        );
        $stmt->execute(['tid' => $tenantId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    /** @param list<array<string, mixed>> $rows */
    public function exportCsv(string $filename, array $rows): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $filename;
        $fp = fopen($path, 'w');
        if ($fp === false) {
            throw new \RuntimeException('Cannot write export file.');
        }
        if ($rows !== []) {
            fputcsv($fp, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($fp, $row);
            }
        }
        fclose($fp);
        return $path;
    }
}
