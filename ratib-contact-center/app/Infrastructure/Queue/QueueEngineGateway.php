<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Queue;

use Ratib\ContactCenter\App\Application\Contracts\QueueGatewayInterface;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Domain\Queue\QueueRealtimeService;
use Ratib\ContactCenter\App\Domain\Routing\AI\RoutingContext;
use Ratib\ContactCenter\App\Domain\Routing\AI\RoutingEngine;

/**
 * Queue + AI routing integration — no direct assignment without RoutingEngine.
 */
final class QueueEngineGateway implements QueueGatewayInterface
{
    private QueueRealtimeService $queueRealtime;
    private RoutingEngine $routingEngine;

    public function __construct(?EventBus $eventBus = null, ?RoutingEngine $routingEngine = null)
    {
        RealtimeOrchestrator::boot($eventBus);
        $bus = $eventBus ?? EventBus::instance();
        $this->queueRealtime = new QueueRealtimeService($bus);
        $this->routingEngine = $routingEngine ?? new RoutingEngine($bus);
    }

    public function enqueueCaller(
        int $tenantId,
        int $callId,
        string $queueCode,
        string $channelId,
        array $context = []
    ): ?array {
        $pdo = Database::connection();

        $queueStmt = $pdo->prepare(
            'SELECT id FROM rcc_queues WHERE tenant_id = :tid AND code = :code AND status = \'active\' LIMIT 1'
        );
        $queueStmt->execute(['tid' => $tenantId, 'code' => $queueCode]);
        $queueId = $queueStmt->fetchColumn();

        if ($queueId === false) {
            error_log('[RCC Queue] Queue not found: ' . $queueCode . ' tenant=' . $tenantId);
            return null;
        }

        $stmt = $pdo->prepare(
            'UPDATE rcc_calls SET queue_id = :qid, status = \'queued\' WHERE id = :cid AND tenant_id = :tid'
        );
        $stmt->execute(['qid' => (int) $queueId, 'cid' => $callId, 'tid' => $tenantId]);

        $this->queueRealtime->onCallerJoined($tenantId, (int) $queueId, $callId, $channelId);

        return $this->assignCall($tenantId, $callId, (int) $queueId, $channelId, array_merge($context, [
            'queue_code' => $queueCode,
        ]));
    }

    public function assignCall(
        int $tenantId,
        int $callId,
        int $queueId,
        string $channelId,
        array $context = []
    ): array {
        $customerPhone = (string) ($context['customer_phone'] ?? $this->callerPhone($tenantId, $callId));

        $routingContext = RoutingContext::fromArray(array_merge($context, [
            'tenant_id' => $tenantId,
            'call_id' => $callId,
            'queue_id' => $queueId,
            'channel_id' => $channelId,
            'customer_phone' => $customerPhone,
        ]));

        $decision = $this->routingEngine->decide($routingContext);

        if ($decision->selectedAgentId > 0) {
            $this->queueRealtime->onAgentAssigned(
                $tenantId,
                $decision->selectedQueueId,
                $callId,
                $decision->selectedAgentId
            );
        }

        return $decision->toArray();
    }

    private function callerPhone(int $tenantId, int $callId): string
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT caller_number FROM rcc_calls WHERE id = :cid AND tenant_id = :tid LIMIT 1'
            );
            $stmt->execute(['cid' => $callId, 'tid' => $tenantId]);
            $phone = $stmt->fetchColumn();
            return $phone !== false ? (string) $phone : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
