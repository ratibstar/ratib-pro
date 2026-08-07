<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Repositories;

use Rateb\App\Marketplace\Models\MarketplaceService;

/** Phase 1 — Service repository stub. */
final class MarketplaceServiceRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForCompany(int $companyId, int $limit = 50): array
    {
        if ($companyId < 1) {
            return [];
        }
        $rows = (new MarketplaceService())->query(
            'SELECT * FROM rateb_mp_services WHERE company_id = :cid AND deleted_at IS NULL'
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
        $row = (new MarketplaceService())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mp_services WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );

        return (int) ($row['c'] ?? 0);
    }
}
