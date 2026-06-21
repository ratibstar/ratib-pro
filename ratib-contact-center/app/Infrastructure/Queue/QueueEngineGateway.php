<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Queue;

use Ratib\ContactCenter\App\Application\Contracts\QueueGatewayInterface;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Domain\Queue\QueueRealtimeService;

/**
 * Integrates IVR route_call → queue with existing Queue Engine tables.
 */
final class QueueEngineGateway implements QueueGatewayInterface
{
    private QueueRealtimeService $queueRealtime;

    public function __construct(?EventBus $eventBus = null)
    {
        RealtimeOrchestrator::boot($eventBus);
        $this->queueRealtime = new QueueRealtimeService($eventBus ?? EventBus::instance());
    }

    public function enqueueCaller(int $tenantId, int $callId, string $queueCode, string $channelId): void
    {
        $pdo = Database::connection();

        $queueStmt = $pdo->prepare(
            'SELECT id FROM rcc_queues WHERE tenant_id = :tid AND code = :code AND status = \'active\' LIMIT 1'
        );
        $queueStmt->execute(['tid' => $tenantId, 'code' => $queueCode]);
        $queueId = $queueStmt->fetchColumn();

        if ($queueId === false) {
            error_log('[RCC Queue] Queue not found: ' . $queueCode . ' tenant=' . $tenantId);
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE rcc_calls SET queue_id = :qid, status = \'queued\' WHERE id = :cid AND tenant_id = :tid'
        );
        $stmt->execute(['qid' => (int) $queueId, 'cid' => $callId, 'tid' => $tenantId]);

        $this->queueRealtime->onCallerJoined($tenantId, (int) $queueId, $callId, $channelId);
    }
}
