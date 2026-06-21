<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Application\Contracts\IvrNodeRepositoryInterface;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Domain\IVR\IvrNode;

final class IvrNodeRepository implements IvrNodeRepositoryInterface
{
    public function findById(int $nodeId, int $flowId): ?IvrNode
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_ivr_nodes WHERE id = :id AND flow_id = :fid LIMIT 1'
        );
        $stmt->execute(['id' => $nodeId, 'fid' => $flowId]);
        $row = $stmt->fetch();
        return $row !== false ? IvrNode::fromRow($row) : null;
    }

    /** @return list<IvrNode> */
    public function findByFlowId(int $flowId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_ivr_nodes WHERE flow_id = :fid ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['fid' => $flowId]);
        $nodes = [];
        foreach ($stmt->fetchAll() as $row) {
            $nodes[] = IvrNode::fromRow($row);
        }
        return $nodes;
    }
}
