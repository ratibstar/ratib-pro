<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessReadRepositoryInterface;

final class MysqlProductCompletenessReadRepository extends BaseRepository implements ProductCompletenessReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_completeness_scores';
    }

    public function listByProductUuid(string $productUuid): array
    {
        $rows = $this->fetchAll(
            'SELECT pcs.locale, pcs.score, pcs.blocking_failed, pcs.failed_rules, pcs.computed_at
             FROM product_completeness_scores pcs
             INNER JOIN products p ON p.id = pcs.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :uuid
             ORDER BY pcs.locale ASC',
            ['uuid' => $productUuid]
        );

        return $this->mapRows($rows);
    }

    public function findByProductAndLocale(string $productUuid, string $locale): ?array
    {
        $row = $this->fetchOne(
            'SELECT pcs.locale, pcs.score, pcs.blocking_failed, pcs.failed_rules, pcs.computed_at
             FROM product_completeness_scores pcs
             INNER JOIN products p ON p.id = pcs.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :uuid AND pcs.locale = :locale
             LIMIT 1',
            ['uuid' => $productUuid, 'locale' => $locale]
        );
        if ($row === null) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function mapRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->mapRow($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        $failed = json_decode((string) ($row['failed_rules'] ?? '[]'), true);
        $row['failed_rules'] = is_array($failed) ? $failed : [];
        $row['blocking_failed'] = (bool) ($row['blocking_failed'] ?? false);
        $row['score'] = (float) ($row['score'] ?? 0);

        return $row;
    }
}
