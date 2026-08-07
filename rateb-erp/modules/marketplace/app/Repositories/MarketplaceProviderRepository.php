<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Repositories;

use Rateb\App\Marketplace\Models\MarketplaceProvider;

/** Phase 1 — Provider repository stub (list only; mutations in later phases). */
final class MarketplaceProviderRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForCompany(int $companyId, int $limit = 50): array
    {
        if ($companyId < 1) {
            return [];
        }
        $rows = (new MarketplaceProvider())->query(
            'SELECT * FROM rateb_mp_providers WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)),
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    public function countForCompany(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        $row = (new MarketplaceProvider())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mp_providers WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );

        return (int) ($row['c'] ?? 0);
    }
}
