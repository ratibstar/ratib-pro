<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFamilyReadRepositoryInterface;

final class MysqlProductFamilyReadRepository extends BaseRepository implements ProductFamilyReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_families';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        $sql = 'SELECT pf.uuid, pf.code, pf.status, pf.created_at, pf.updated_at,
                       b.uuid AS brand_uuid,
                       ' . $this->translationSelect('ft', 'name') . ',
                       ' . $this->translationSelect('ft', 'description') . ',
                       COALESCE(ft_loc.language_code, ft_fb.language_code) AS resolved_language_code
                FROM product_families pf
                LEFT JOIN brands b ON b.id = pf.brand_id AND b.deleted_at IS NULL
                ' . $this->translationJoin('pf', 'id', 'family_translations', 'ft', 'product_family_id') . '
                WHERE pf.uuid = :uuid AND ' . $this->notDeletedClause('pf') . '
                LIMIT 1';

        return $this->fetchOne($sql, array_merge(['uuid' => $uuid], $this->localeParams($locale)));
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT pf.uuid, pf.code, pf.status,
                       b.uuid AS brand_uuid,
                       ' . $this->translationSelect('ft', 'name') . ',
                       ' . $this->translationSelect('ft', 'description') . ',
                       COALESCE(ft_loc.language_code, ft_fb.language_code) AS resolved_language_code
                FROM product_families pf
                LEFT JOIN brands b ON b.id = pf.brand_id AND b.deleted_at IS NULL
                ' . $this->translationJoin('pf', 'id', 'family_translations', 'ft', 'product_family_id') . '
                WHERE ' . $this->notDeletedClause('pf') . '
                ORDER BY name ASC, pf.id ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->fetchAll($sql, $this->localeParams($locale));
    }
}
