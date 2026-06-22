<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Supervisor;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Domain\Agents\AgentStateService;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor\SupervisorAlertRepository;

final class SupervisorDashboardService
{
    public function __construct(
        private readonly AgentStateService $agents = new AgentStateService(),
        private readonly QueueRealtimeService $queues = new QueueRealtimeService(),
        private readonly SupervisorAlertRepository $alerts = new SupervisorAlertRepository()
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(int $tenantId): array
    {
        $pdo = Database::connection();

        $agentStates = $this->agents->listByTenant($tenantId);
        $ready = $busy = $paused = $offline = 0;
        foreach ($agentStates as $s) {
            match ($s['status'] ?? '') {
                'ready' => $ready++,
                'busy', 'wrapup' => $busy++,
                'paused' => $paused++,
                default => $offline++,
            };
        }

        $queueRows = $pdo->prepare("SELECT id FROM rcc_queues WHERE tenant_id=:tid AND status='active'");
        $queueRows->execute(['tid' => $tenantId]);
        $snapshots = [];
        $totalWaiting = 0;
        $slaRed = 0;
        foreach ($queueRows->fetchAll() as $q) {
            $snap = $this->queues->computeSnapshot($tenantId, (int) $q['id']);
            if ($snap !== []) {
                $snapshots[] = $snap;
                $totalWaiting += (int) ($snap['waiting_count'] ?? 0);
                if (($snap['sla_risk'] ?? '') === 'red') {
                    $slaRed++;
                }
            }
        }

        $openAlerts = count($this->alerts->list($tenantId, true, 50));

        $convStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rcc_conversations WHERE tenant_id=:tid AND status IN ('open','pending')"
        );
        $convStmt->execute(['tid' => $tenantId]);
        $openConversations = (int) $convStmt->fetchColumn();

        $result = [
            'agents' => [
                'total' => count($agentStates),
                'ready' => $ready,
                'busy' => $busy,
                'paused' => $paused,
                'offline' => $offline,
            ],
            'queues' => [
                'active' => count($snapshots),
                'total_waiting' => $totalWaiting,
                'sla_red_queues' => $slaRed,
                'snapshots' => $snapshots,
            ],
            'conversations' => ['open' => $openConversations],
            'alerts' => ['open' => $openAlerts],
            'timestamp' => gmdate('c'),
        ];

        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_DASHBOARD_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => $result,
        ]);

        return $result;
    }
}
