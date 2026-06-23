<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\DisasterRecovery;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class PbxClusterService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    /** @param array<string, mixed> $data */
    public function createCluster(?int $tenantId, array $data, ?int $userId): array
    {
        Database::connection()->prepare(
            'INSERT INTO rcc_pbx_clusters (tenant_id, name, region, ha_mode) VALUES (:tid, :name, :region, :mode)'
        )->execute([
            'tid' => $tenantId,
            'name' => (string) ($data['name'] ?? 'RCC Cluster'),
            'region' => (string) ($data['region'] ?? 'ksa-central'),
            'mode' => (string) ($data['ha_mode'] ?? 'active_passive'),
        ]);
        $id = (int) Database::connection()->lastInsertId();
        $this->audit->log($tenantId ?? 0, 'pbx.cluster.created', $userId, 'pbx_cluster', $id);
        return $this->findCluster($id) ?? [];
    }

    /** @param array<string, mixed> $data */
    public function addNode(int $clusterId, array $data, ?int $userId): array
    {
        Database::connection()->prepare(
            'INSERT INTO rcc_pbx_cluster_nodes (cluster_id, node_name, host, ami_port, sip_port, role, weight)
             VALUES (:cid, :name, :host, :ami, :sip, :role, :w)'
        )->execute([
            'cid' => $clusterId,
            'name' => (string) ($data['node_name'] ?? 'node-1'),
            'host' => (string) ($data['host'] ?? '127.0.0.1'),
            'ami' => (int) ($data['ami_port'] ?? 5038),
            'sip' => (int) ($data['sip_port'] ?? 5060),
            'role' => (string) ($data['role'] ?? 'primary'),
            'w' => (int) ($data['weight'] ?? 100),
        ]);
        $id = (int) Database::connection()->lastInsertId();
        EventBus::instance()->emit([
            'type' => EventType::PBX_CLUSTER_UPDATED,
            'tenant_id' => 0,
            'payload' => ['cluster_id' => $clusterId, 'node_id' => $id],
        ]);
        return ['node_id' => $id];
    }

    public function failover(int $clusterId, string $fromNode, string $toNode, ?int $userId): array
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO rcc_failover_events (cluster_id, event_type, from_node, to_node, status) VALUES (:cid, \'failover\', :from, :to, \'started\')'
        )->execute(['cid' => $clusterId, 'from' => $fromNode, 'to' => $toNode]);
        $eventId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE rcc_pbx_clusters SET status = 'failover', primary_node_id = (
            SELECT id FROM rcc_pbx_cluster_nodes WHERE cluster_id = :cid AND node_name = :to LIMIT 1
        ) WHERE id = :cid")->execute(['cid' => $clusterId, 'to' => $toNode]);
        $pdo->prepare('UPDATE rcc_failover_events SET status = \'completed\', completed_at = NOW() WHERE id = :id')
            ->execute(['id' => $eventId]);
        $this->audit->log(0, 'pbx.failover', $userId, 'failover_event', $eventId);
        EventBus::instance()->emit([
            'type' => EventType::PBX_FAILOVER,
            'tenant_id' => 0,
            'payload' => ['cluster_id' => $clusterId, 'from' => $fromNode, 'to' => $toNode],
        ]);
        return ['event_id' => $eventId, 'status' => 'completed'];
    }

    public function findCluster(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_pbx_clusters WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listClusters(?int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_pbx_clusters WHERE tenant_id IS NULL OR tenant_id = :tid ORDER BY id'
        );
        $stmt->execute(['tid' => $tenantId ?? 0]);
        return $stmt->fetchAll() ?: [];
    }
}
