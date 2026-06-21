<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Queue;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;

/**
 * Live queue aggregator — computes waiting count, longest wait, SLA risk, agent load.
 * Emits QUEUE_* and SLA_ALERT events via EventBus only.
 */
final class QueueRealtimeService implements EventSubscriberInterface
{
    private EventBus $eventBus;

    public function __construct(?EventBus $eventBus = null)
    {
        $this->eventBus = $eventBus ?? EventBus::instance();
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if (in_array($event->type, [
            EventType::QUEUE_JOINED,
            EventType::QUEUE_ASSIGNED,
            EventType::CALL_ENDED,
            EventType::AGENT_READY,
            EventType::AGENT_BUSY,
            EventType::AGENT_OFFLINE,
        ], true)) {
            $queueId = $event->queueId;
            if ($queueId === null && isset($event->payload['queue_id'])) {
                $queueId = (int) $event->payload['queue_id'];
            }
            if ($queueId !== null && $queueId > 0) {
                $this->publishSnapshot($event->tenantId, $queueId);
            } else {
                $this->publishTenantSnapshots($event->tenantId);
            }
        }
    }

    public function onCallerJoined(int $tenantId, int $queueId, int $callId, string $channelId): void
    {
        $this->eventBus->emit([
            'type' => EventType::QUEUE_JOINED,
            'tenant_id' => $tenantId,
            'queue_id' => $queueId,
            'call_id' => $callId,
            'payload' => [
                'channel_id' => $channelId,
                'joined_at' => gmdate('c'),
            ],
        ]);
        $this->publishSnapshot($tenantId, $queueId);
    }

    public function onAgentAssigned(int $tenantId, int $queueId, int $callId, int $agentId): void
    {
        $this->eventBus->emit([
            'type' => EventType::QUEUE_ASSIGNED,
            'tenant_id' => $tenantId,
            'queue_id' => $queueId,
            'call_id' => $callId,
            'agent_id' => $agentId,
            'payload' => ['assigned_at' => gmdate('c')],
        ]);
        $this->publishSnapshot($tenantId, $queueId);
    }

    /** @return array<string, mixed> */
    public function computeSnapshot(int $tenantId, int $queueId): array
    {
        $pdo = Database::connection();

        $queueStmt = $pdo->prepare(
            'SELECT id, code, name, sla_target_seconds FROM rcc_queues
             WHERE id = :qid AND tenant_id = :tid LIMIT 1'
        );
        $queueStmt->execute(['qid' => $queueId, 'tid' => $tenantId]);
        $queue = $queueStmt->fetch();
        if ($queue === false) {
            return [];
        }

        $slaTarget = (int) ($queue['sla_target_seconds'] ?? 300);

        $waitingStmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt,
                    COALESCE(MAX(TIMESTAMPDIFF(SECOND, started_at, NOW())), 0) AS longest_wait,
                    COALESCE(AVG(TIMESTAMPDIFF(SECOND, started_at, NOW())), 0) AS avg_wait
             FROM rcc_calls
             WHERE tenant_id = :tid AND queue_id = :qid AND status IN ('queued','ringing')"
        );
        $waitingStmt->execute(['tid' => $tenantId, 'qid' => $queueId]);
        $wait = $waitingStmt->fetch() ?: ['cnt' => 0, 'longest_wait' => 0, 'avg_wait' => 0];

        $agentsStmt = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN status = 'busy' THEN 1 ELSE 0 END) AS busy,
                COUNT(*) AS total
             FROM rcc_agent_live_state
             WHERE tenant_id = :tid AND queue_id = :qid"
        );
        $agentsStmt->execute(['tid' => $tenantId, 'qid' => $queueId]);
        $agents = $agentsStmt->fetch() ?: ['available' => 0, 'busy' => 0, 'total' => 0];

        $longestWait = (int) $wait['longest_wait'];
        $waitingCount = (int) $wait['cnt'];
        $availableAgents = (int) $agents['available'];
        $busyAgents = (int) $agents['busy'];

        $slaRisk = $this->slaRiskLevel($longestWait, $slaTarget, $waitingCount, $availableAgents);
        $distributionLoad = $availableAgents > 0
            ? round($waitingCount / $availableAgents, 2)
            : (float) $waitingCount;

        return [
            'queue_id' => $queueId,
            'queue_code' => (string) $queue['code'],
            'queue_name' => (string) $queue['name'],
            'waiting_count' => $waitingCount,
            'longest_wait_seconds' => $longestWait,
            'avg_wait_seconds' => (int) round((float) $wait['avg_wait']),
            'available_agents' => $availableAgents,
            'busy_agents' => $busyAgents,
            'sla_target_seconds' => $slaTarget,
            'sla_risk' => $slaRisk,
            'distribution_load' => $distributionLoad,
            'computed_at' => gmdate('c'),
        ];
    }

    public function publishSnapshot(int $tenantId, int $queueId): void
    {
        $snapshot = $this->computeSnapshot($tenantId, $queueId);
        if ($snapshot === []) {
            return;
        }

        $this->eventBus->emit([
            'type' => EventType::QUEUE_SNAPSHOT,
            'tenant_id' => $tenantId,
            'queue_id' => $queueId,
            'payload' => $snapshot,
        ]);

        $this->eventBus->emit([
            'type' => EventType::QUEUE_WAIT_TIME_UPDATED,
            'tenant_id' => $tenantId,
            'queue_id' => $queueId,
            'payload' => [
                'longest_wait_seconds' => $snapshot['longest_wait_seconds'],
                'avg_wait_seconds' => $snapshot['avg_wait_seconds'],
                'waiting_count' => $snapshot['waiting_count'],
            ],
        ]);

        if ($snapshot['sla_risk'] !== 'green') {
            $this->eventBus->emit([
                'type' => EventType::SLA_ALERT,
                'tenant_id' => $tenantId,
                'queue_id' => $queueId,
                'payload' => [
                    'level' => $snapshot['sla_risk'],
                    'longest_wait_seconds' => $snapshot['longest_wait_seconds'],
                    'sla_target_seconds' => $snapshot['sla_target_seconds'],
                    'waiting_count' => $snapshot['waiting_count'],
                    'available_agents' => $snapshot['available_agents'],
                ],
            ]);
        }
    }

    public function publishTenantSnapshots(int $tenantId): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM rcc_queues WHERE tenant_id = :tid AND status = \'active\''
        );
        $stmt->execute(['tid' => $tenantId]);
        foreach ($stmt->fetchAll() as $row) {
            $this->publishSnapshot($tenantId, (int) $row['id']);
        }
    }

    private function slaRiskLevel(int $longestWait, int $slaTarget, int $waiting, int $available): string
    {
        if ($waiting === 0) {
            return 'green';
        }
        $ratio = $slaTarget > 0 ? $longestWait / $slaTarget : 1.0;
        if ($ratio >= 1.0 || ($waiting > 0 && $available === 0)) {
            return 'red';
        }
        if ($ratio >= 0.7) {
            return 'yellow';
        }
        return 'green';
    }
}
