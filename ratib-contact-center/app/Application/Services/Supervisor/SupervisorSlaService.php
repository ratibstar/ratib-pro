<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Supervisor;

use Ratib\ContactCenter\App\Application\Services\ReportService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class SupervisorSlaService
{
    public function __construct(
        private readonly ReportService $reports = new ReportService()
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(int $tenantId, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?? gmdate('Y-m-d 00:00:00');
        $to = $to ?? gmdate('Y-m-d 23:59:59');
        $pdo = Database::connection();

        $byQueue = $this->reports->queuePerformance($tenantId, $from, $to);
        $routingSla = $this->reports->slaReport($tenantId, $from, $to);

        $convStmt = $pdo->prepare(
            "SELECT sla_status, COUNT(*) AS cnt FROM rcc_conversations
             WHERE tenant_id=:tid AND created_at BETWEEN :from AND :to
             GROUP BY sla_status"
        );
        $convStmt->execute(['tid' => $tenantId, 'from' => $from, 'to' => $to]);
        $conversationSla = $convStmt->fetchAll() ?: [];

        $liveStmt = $pdo->prepare(
            "SELECT q.id, q.code, q.name, q.sla_target_seconds,
                    COUNT(c.id) AS waiting,
                    COALESCE(MAX(TIMESTAMPDIFF(SECOND, c.started_at, NOW())), 0) AS longest_wait
             FROM rcc_queues q
             LEFT JOIN rcc_calls c ON c.queue_id = q.id AND c.tenant_id = q.tenant_id AND c.status IN ('queued','ringing')
             WHERE q.tenant_id = :tid AND q.status = 'active'
             GROUP BY q.id, q.code, q.name, q.sla_target_seconds"
        );
        $liveStmt->execute(['tid' => $tenantId]);
        $liveQueues = $liveStmt->fetchAll() ?: [];

        $data = [
            'period' => ['from' => $from, 'to' => $to],
            'queue_performance' => $byQueue,
            'routing_sla' => $routingSla,
            'conversation_sla' => $conversationSla,
            'live_queues' => $liveQueues,
            'timestamp' => gmdate('c'),
        ];

        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_SLA_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['period' => $data['period']],
        ]);

        return $data;
    }
}
