<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Supervisor;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Domain\Agents\AgentStateService;
use Ratib\ContactCenter\App\Domain\Queue\QueueRealtimeService;

final class SupervisorMonitorService
{
    public function __construct(
        private readonly QueueRealtimeService $queues = new QueueRealtimeService(),
        private readonly AgentStateService $agents = new AgentStateService()
    ) {
    }

    /** @return array<string, mixed> */
    public function wallboard(int $tenantId): array
    {
        $pdo = Database::connection();
        $queueIds = $pdo->prepare("SELECT id, code, name, name_ar FROM rcc_queues WHERE tenant_id=:tid AND status='active'");
        $queueIds->execute(['tid' => $tenantId]);
        $queuePanels = [];
        foreach ($queueIds->fetchAll() as $q) {
            $snap = $this->queues->computeSnapshot($tenantId, (int) $q['id']);
            $queuePanels[] = array_merge($snap, [
                'name_ar' => $q['name_ar'] ?? null,
            ]);
        }

        $agentRows = $pdo->prepare(
            "SELECT a.id, a.display_name, a.extension, als.status, als.pause_reason, als.current_call_id, als.queue_id
             FROM rcc_agents a
             LEFT JOIN rcc_agent_live_state als ON als.agent_id = a.id AND als.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tid AND a.status = 'active'
             ORDER BY a.display_name"
        );
        $agentRows->execute(['tid' => $tenantId]);
        $agents = $agentRows->fetchAll() ?: [];

        $data = [
            'queues' => $queuePanels,
            'agents' => $agents,
            'totals' => [
                'waiting' => array_sum(array_column($queuePanels, 'waiting_count')),
                'ready_agents' => count(array_filter($agents, static fn ($a) => ($a['status'] ?? '') === 'ready')),
                'busy_agents' => count(array_filter($agents, static fn ($a) => in_array($a['status'] ?? '', ['busy', 'wrapup'], true))),
            ],
            'timestamp' => gmdate('c'),
        ];

        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_WALLBOARD_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => $data,
        ]);

        return $data;
    }

    /** @return array<string, mixed> */
    public function queueMonitor(int $tenantId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT id, code, name FROM rcc_queues WHERE tenant_id=:tid AND status='active' ORDER BY code");
        $stmt->execute(['tid' => $tenantId]);
        $queues = [];
        foreach ($stmt->fetchAll() as $q) {
            $qid = (int) $q['id'];
            $snap = $this->queues->computeSnapshot($tenantId, $qid);
            $waitingCalls = $pdo->prepare(
                "SELECT id, uuid, caller_number, status, started_at,
                        TIMESTAMPDIFF(SECOND, started_at, NOW()) AS wait_seconds
                 FROM rcc_calls WHERE tenant_id=:tid AND queue_id=:qid AND status IN ('queued','ringing')
                 ORDER BY started_at ASC LIMIT 50"
            );
            $waitingCalls->execute(['tid' => $tenantId, 'qid' => $qid]);
            $queues[] = [
                'queue' => $q,
                'snapshot' => $snap,
                'waiting_calls' => $waitingCalls->fetchAll() ?: [],
            ];
        }
        return ['queues' => $queues, 'timestamp' => gmdate('c')];
    }

    /** @return array<string, mixed> */
    public function agentMonitor(int $tenantId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT a.id, a.display_name, a.extension, a.email, a.is_senior,
                    als.status, als.pause_reason, als.current_call_id, als.queue_id, als.session_started_at, als.last_update,
                    (SELECT COUNT(*) FROM rcc_softphone_calls sc
                     WHERE sc.agent_id = a.id AND sc.tenant_id = a.tenant_id AND sc.status NOT IN ('ended','failed')
                     AND DATE(sc.started_at) = CURDATE()) AS active_calls_today
             FROM rcc_agents a
             LEFT JOIN rcc_agent_live_state als ON als.agent_id = a.id AND als.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tid AND a.status = 'active'
             ORDER BY als.status DESC, a.display_name"
        );
        $stmt->execute(['tid' => $tenantId]);
        return ['agents' => $stmt->fetchAll() ?: [], 'timestamp' => gmdate('c')];
    }
}
