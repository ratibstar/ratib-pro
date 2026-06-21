<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Application\Contracts\IvrFlowRepositoryInterface;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Domain\IVR\IvrFlow;

final class IvrFlowRepository implements IvrFlowRepositoryInterface
{
    public function findActiveByTenant(int $tenantId): ?IvrFlow
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_ivr_flows
             WHERE tenant_id = :tid AND is_active = 1
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row !== false ? IvrFlow::fromRow($row) : null;
    }

    public function findById(int $flowId, int $tenantId): ?IvrFlow
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_ivr_flows WHERE id = :id AND tenant_id = :tid LIMIT 1'
        );
        $stmt->execute(['id' => $flowId, 'tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row !== false ? IvrFlow::fromRow($row) : null;
    }

    public function updateEntryNode(int $flowId, int $entryNodeId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_ivr_flows SET entry_node_id = :nid, updated_at = NOW() WHERE id = :fid'
        );
        $stmt->execute(['nid' => $entryNodeId, 'fid' => $flowId]);
    }
}
