<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Contracts;

use Ratib\ContactCenter\App\Domain\IVR\IvrFlow;

interface IvrFlowRepositoryInterface
{
    public function findActiveByTenant(int $tenantId): ?IvrFlow;

    public function findById(int $flowId, int $tenantId): ?IvrFlow;

    public function updateEntryNode(int $flowId, int $entryNodeId): void;
}
