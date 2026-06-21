<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Contracts;

use Ratib\ContactCenter\App\Domain\IVR\IvrNode;

interface IvrNodeRepositoryInterface
{
    public function findById(int $nodeId, int $flowId): ?IvrNode;

    /** @return list<IvrNode> */
    public function findByFlowId(int $flowId): array;
}
