<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Audit;

use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;

final class InfrastructureAuditLogger
{
    private \PDO $pdo;
    private InfrastructureEventEmitter $events;

    public function __construct(\PDO $pdo, InfrastructureEventEmitter $events) {
        $this->pdo = $pdo;
        $this->events = $events;
    }


    /**
     * @param array<string, mixed> $payload
     */
    public function appendImmutable(string $actionType, array $payload): void
    {
        $sql = 'INSERT INTO ratib_infra_audit_entries (action_type, actor_id, tenant_id, payload_json, created_at)
                VALUES (:action_type, :actor_id, :tenant_id, :payload_json, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'action_type' => substr($actionType, 0, 64),
            'actor_id' => (string) ($payload['actor'] ?? 'system'),
            'tenant_id' => isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);

        $this->events->structuredLog('info', 'Infrastructure audit entry recorded', [
            'action_type' => $actionType,
        ]);
    }
}

