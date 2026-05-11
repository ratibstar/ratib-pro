<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning\Execution;

use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;

final class OrphanResourceReconciler
{
    private \PDO $pdo;
    private InfrastructureAuditLogger $audit;

    public function __construct(\PDO $pdo, InfrastructureAuditLogger $audit) {
        $this->pdo = $pdo;
        $this->audit = $audit;
    }


    public function snapshot(): array
    {
        $sql = 'SELECT s.public_id
                FROM ratib_infra_services s
                LEFT JOIN ratib_infra_orders o ON o.public_id = s.order_public_id
                WHERE o.public_id IS NULL
                LIMIT 100';
        $rows = $this->pdo->query($sql);
        $ids = [];
        if ($rows instanceof \PDOStatement) {
            while ($r = $rows->fetch(\PDO::FETCH_ASSOC)) {
                if (!is_array($r)) {
                    continue;
                }
                $ids[] = (string) ($r['public_id'] ?? '');
            }
        }
        $this->audit->appendImmutable('orphan_resource_snapshot', [
            'actor' => 'system',
            'count' => count($ids),
        ]);
        return ['count' => count($ids), 'service_public_ids' => $ids];
    }
}

