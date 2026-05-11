<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Catalog;

final class CatalogRepository
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listVisibleForTenant(?int $tenantId): array
    {
        $sql = 'SELECT * FROM ratib_infra_catalog_items
                WHERE is_active = 1 AND (tenant_id IS NULL OR tenant_id = :tenant_id)
                ORDER BY tenant_id IS NULL DESC, id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}

