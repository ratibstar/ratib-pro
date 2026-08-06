<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\Integration;

use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Contracts\StockMovementReferenceLookup;
use Rateb\App\Models\StockMovement;

final class ErpStockMovementReferenceLookup implements StockMovementReferenceLookup
{
    public function existsForReference(int $companyId, string $referenceType, int $referenceId): bool
    {
        return $this->idsForReference($companyId, $referenceType, $referenceId) !== [];
    }

    /** @return list<int> */
    public function idsForReference(int $companyId, string $referenceType, int $referenceId): array
    {
        if ($companyId < 1 || $referenceId < 1 || $referenceType === '') {
            return [];
        }
        TenantContext::setCompanyId($companyId);
        $rows = (new StockMovement())->query(
            'SELECT id FROM rateb_stock_movements
             WHERE company_id = :cid AND reference_type = :rt AND reference_id = :rid
             ORDER BY id ASC',
            [
                'cid' => $companyId,
                'rt' => $referenceType,
                'rid' => $referenceId,
            ]
        );
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
