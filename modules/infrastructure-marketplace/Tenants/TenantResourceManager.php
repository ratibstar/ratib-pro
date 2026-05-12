<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Tenants;

use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\State\StateNamespaceRegistry;

/**
 * Overlay ownership for tenant resources (additive to ratib_infra_services).
 */
final class TenantResourceManager
{
    public function __construct(
        private \PDO $pdo,
        private ?InfrastructureAuditLogger $audit = null
    ) {
        if ($this->audit === null) {
            $this->audit = new InfrastructureAuditLogger($pdo, new InfrastructureEventEmitter());
        }
    }

    /**
     * @param array<string, mixed> $row resource_type, resource_id required; optional commerce ids, graph node, metadata
     */
    public function assign(int $tenantId, array $row, string $actor, ?string $correlationId = null): int
    {
        $agencyId = isset($row['agency_id']) ? (int) $row['agency_id'] : null;
        $type = (string) ($row['resource_type'] ?? '');
        $rid = (string) ($row['resource_id'] ?? '');
        if ($type === '' || $rid === '') {
            throw new \InvalidArgumentException('resource_type and resource_id required.');
        }
        $sql = 'INSERT INTO ratib_tenant_resources (
            tenant_id, agency_id, resource_type, resource_id,
            commerce_product_id, commerce_plan_id, ownership_state, linked_graph_node, metadata_json, created_at, updated_at
        ) VALUES (
            :tenant_id, :agency_id, :resource_type, :resource_id,
            :commerce_product_id, :commerce_plan_id, :ownership_state, :linked_graph_node, :metadata_json, NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            ownership_state = VALUES(ownership_state),
            commerce_product_id = VALUES(commerce_product_id),
            commerce_plan_id = VALUES(commerce_plan_id),
            linked_graph_node = VALUES(linked_graph_node),
            metadata_json = VALUES(metadata_json),
            updated_at = NOW()';
        $ownership = StateNamespaceRegistry::normalize((string) ($row['ownership_state'] ?? 'OWNED'));
        $warnings = StateNamespaceRegistry::validateOwnershipState($ownership);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'agency_id' => $agencyId,
            'resource_type' => $type,
            'resource_id' => $rid,
            'commerce_product_id' => $row['commerce_product_id'] ?? null,
            'commerce_plan_id' => $row['commerce_plan_id'] ?? null,
            'ownership_state' => $ownership,
            'linked_graph_node' => $row['linked_graph_node'] ?? null,
            'metadata_json' => isset($row['metadata_json'])
                ? (is_array($row['metadata_json']) ? json_encode($row['metadata_json'], JSON_UNESCAPED_SLASHES) : (string) $row['metadata_json'])
                : null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id === 0) {
            $sel = $this->pdo->prepare(
                'SELECT id FROM ratib_tenant_resources WHERE tenant_id = :t AND resource_type = :ty AND resource_id = :rid LIMIT 1'
            );
            $sel->execute(['t' => $tenantId, 'ty' => $type, 'rid' => $rid]);
            $id = (int) ($sel->fetchColumn() ?: 0);
        }
        $this->audit->appendImmutable('tenant_resource_assign', [
            'actor' => $actor,
            'tenant_id' => $tenantId,
            'resource_id' => $id,
            'warnings' => $warnings,
            'correlation_id' => $correlationId,
        ]);

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByTenant(int $tenantId, ?string $ownershipState = null): array
    {
        if ($ownershipState !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM ratib_tenant_resources WHERE tenant_id = :t AND ownership_state = :o ORDER BY id ASC'
            );
            $stmt->execute(['t' => $tenantId, 'o' => StateNamespaceRegistry::normalize($ownershipState)]);
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM ratib_tenant_resources WHERE tenant_id = :t ORDER BY id ASC');
            $stmt->execute(['t' => $tenantId]);
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, string $resourceType, string $resourceId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ratib_tenant_resources WHERE tenant_id = :t AND resource_type = :ty AND resource_id = :rid LIMIT 1'
        );
        $stmt->execute(['t' => $tenantId, 'ty' => $resourceType, 'rid' => $resourceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Commerce-only ownership state change (not queue).
     *
     * @return list<string> warnings
     */
    public function setOwnershipState(
        int $id,
        string $toState,
        string $actor,
        ?string $correlationId = null
    ): array {
        $warnings = StateNamespaceRegistry::validateOwnershipState(StateNamespaceRegistry::normalize($toState));
        $stmt = $this->pdo->prepare('UPDATE ratib_tenant_resources SET ownership_state = :s, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['s' => StateNamespaceRegistry::normalize($toState), 'id' => $id]);
        $this->audit->appendImmutable('tenant_resource_ownership', [
            'actor' => $actor,
            'tenant_id' => null,
            'resource_row_id' => $id,
            'to' => $toState,
            'correlation_id' => $correlationId,
        ]);

        return $warnings;
    }
}
