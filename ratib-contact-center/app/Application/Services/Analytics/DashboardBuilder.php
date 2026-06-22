<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Analytics;

use Ratib\ContactCenter\App\Application\Services\Supervisor\SupervisorDashboardService;
use Ratib\ContactCenter\App\Application\Services\Supervisor\SupervisorWfmService;
use Ratib\ContactCenter\App\Core\Database;

final class DashboardBuilder
{
    public function __construct(
        private readonly KpiEngine $kpis = new KpiEngine(),
        private readonly SupervisorDashboardService $supervisor = new SupervisorDashboardService(),
        private readonly SupervisorWfmService $wfm = new SupervisorWfmService()
    ) {
    }

    /** @return array<string, mixed> */
    public function executive(int $tenantId): array
    {
        $widgets = $this->widgets($tenantId, 'executive');
        $summary = $this->supervisor->summary($tenantId);
        $occupancy = $this->wfm->occupancy($tenantId);
        $pdo = Database::connection();
        $openStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rcc_tickets WHERE tenant_id = :tid AND status IN ('open','in_progress','pending')"
        );
        $openStmt->execute(['tid' => $tenantId]);
        $openTickets = (int) $openStmt->fetchColumn();
        $accStmt = $pdo->prepare("SELECT COUNT(*) FROM rcc_accounts WHERE tenant_id = :tid AND status = 'active'");
        $accStmt->execute(['tid' => $tenantId]);
        $accounts = (int) $accStmt->fetchColumn();

        return [
            'widgets' => $widgets,
            'kpis' => $this->kpis->evaluate($tenantId),
            'live' => [
                'agents' => $summary['agents'] ?? [],
                'queues' => $summary['queues'] ?? [],
                'occupancy_pct' => $occupancy['occupancy_pct'] ?? 0,
                'open_tickets' => $openTickets,
                'crm_accounts' => $accounts,
            ],
            'timestamp' => gmdate('c'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function widgets(int $tenantId, string $dashboardKey): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_dashboard_widgets WHERE tenant_id = :tid AND dashboard_key = :key AND is_active = 1 ORDER BY sort_order'
        );
        $stmt->execute(['tid' => $tenantId, 'key' => $dashboardKey]);
        return $stmt->fetchAll() ?: [];
    }
}
