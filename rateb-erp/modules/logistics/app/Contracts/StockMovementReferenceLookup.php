<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Contracts;

interface StockMovementReferenceLookup
{
    public function existsForReference(int $companyId, string $referenceType, int $referenceId): bool;

    /** @return list<int> */
    public function idsForReference(int $companyId, string $referenceType, int $referenceId): array;
}
