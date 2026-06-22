<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Analytics;

use Ratib\ContactCenter\App\Core\Database;

final class KpiEngine
{
    /** @return list<array<string, mixed>> */
    public function evaluate(int $tenantId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_kpis WHERE tenant_id = :tid AND is_active = 1');
        $stmt->execute(['tid' => $tenantId]);
        $results = [];
        foreach ($stmt->fetchAll() ?: [] as $kpi) {
            $current = $this->currentValue($tenantId, (string) $kpi['kpi_key']);
            $status = 'ok';
            if ($kpi['critical_below'] !== null && $current < (float) $kpi['critical_below']) {
                $status = 'critical';
            } elseif ($kpi['warning_below'] !== null && $current < (float) $kpi['warning_below']) {
                $status = 'warning';
            }
            $results[] = [
                'kpi' => $kpi,
                'current' => $current,
                'status' => $status,
            ];
        }
        return $results;
    }

    private function currentValue(int $tenantId, string $key): float
    {
        $pdo = Database::connection();
        return match ($key) {
            'sla_pct' => $this->slaPct($pdo, $tenantId),
            'occupancy_pct' => $this->occupancyPct($pdo, $tenantId),
            'ticket_backlog' => $this->ticketBacklog($pdo, $tenantId),
            default => 0.0,
        };
    }

    private function ticketBacklog(\PDO $pdo, int $tenantId): float
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rcc_tickets WHERE tenant_id = :tid AND status IN ('open','in_progress','pending')"
        );
        $stmt->execute(['tid' => $tenantId]);
        return (float) ($stmt->fetchColumn() ?: 0);
    }

    private function slaPct(\PDO $pdo, int $tenantId): float
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total, SUM(CASE WHEN resolution_met = 1 OR breached_at IS NULL THEN 1 ELSE 0 END) AS met
             FROM rcc_ticket_sla WHERE tenant_id = :tid'
        );
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        $total = (int) ($row['total'] ?? 0);
        if ($total === 0) {
            return 100.0;
        }
        return round(((int) ($row['met'] ?? 0) / $total) * 100, 2);
    }

    private function occupancyPct(\PDO $pdo, int $tenantId): float
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN als.status IN ('busy','wrapup') THEN 1 ELSE 0 END) AS occupied
             FROM rcc_agents a
             LEFT JOIN rcc_agent_live_state als ON als.agent_id = a.id AND als.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tid AND a.status = 'active'"
        );
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        $total = (int) ($row['total'] ?? 0);
        if ($total === 0) {
            return 0.0;
        }
        return round(((int) ($row['occupied'] ?? 0) / $total) * 100, 2);
    }
}
