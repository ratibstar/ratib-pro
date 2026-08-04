<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\DTO\ProductListFilter;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;

final class MysqlProductReadRepository extends BaseRepository implements ProductReadRepositoryInterface
{
    protected function table(): string
    {
        return 'products';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        $sql = $this->baseSelectSql() . '
                WHERE p.uuid = :uuid AND ' . $this->notDeletedClause('p') . '
                LIMIT 1';

        return $this->fetchOne($sql, array_merge(['uuid' => $uuid], $this->localeParams($locale)));
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        return $this->listFiltered($locale, new ProductListFilter(), $limit, $offset);
    }

    public function listFiltered(
        LocaleContext $locale,
        ProductListFilter $filter,
        int $limit = 100,
        int $offset = 0
    ): array {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $where = [$this->notDeletedClause('p')];
        $params = $this->localeParams($locale);

        if ($filter->status !== null && $filter->status !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $filter->status;
        }
        if ($filter->categoryUuid !== null && $filter->categoryUuid !== '') {
            $where[] = 'c.uuid = :category_uuid';
            $params['category_uuid'] = $filter->categoryUuid;
        }
        if ($filter->brandUuid !== null && $filter->brandUuid !== '') {
            $where[] = 'b.uuid = :brand_uuid';
            $params['brand_uuid'] = $filter->brandUuid;
        }
        if ($filter->familyUuid !== null && $filter->familyUuid !== '') {
            $where[] = 'pf.uuid = :family_uuid';
            $params['family_uuid'] = $filter->familyUuid;
        }
        if ($filter->sku !== null && $filter->sku !== '') {
            $where[] = 'p.sku LIKE :sku';
            $params['sku'] = '%' . $filter->sku . '%';
        }

        $sql = $this->baseSelectSql() . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.id DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->fetchAll($sql, $params);
    }

    public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        return $this->listFiltered(
            $locale,
            new ProductListFilter(familyUuid: $familyUuid),
            $limit,
            $offset
        );
    }

    public function findLockVersion(string $uuid): ?int
    {
        $row = $this->fetchOne(
            'SELECT lock_version FROM products WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $uuid]
        );

        return $row !== null ? (int) $row['lock_version'] : null;
    }

    public function findWorkflowMeta(string $uuid): ?array
    {
        return $this->fetchOne(
            'SELECT p.id, p.uuid, p.sku, p.status, p.lock_version, p.version_number, p.category_id, c.uuid AS category_uuid
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
             WHERE p.uuid = :uuid AND p.deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    private function baseSelectSql(): string
    {
        return 'SELECT p.uuid, p.sku, p.is_bundle, p.primary_barcode, p.weight_kg, p.length_cm, p.width_cm, p.height_cm,
                       p.manufacturer_id, p.country_id, p.warranty_months, p.tax_class, p.status,
                       p.version_number, p.lock_version, p.publish_at, p.archive_at, p.published_at,
                       p.approved_by, p.approved_at, p.search_weight, p.boost_score,
                       p.created_at, p.updated_at,
                       b.uuid AS brand_uuid, c.uuid AS category_uuid, pf.uuid AS family_uuid, u.uuid AS unit_uuid,
                       COALESCE(ct_loc.name, ct_fb.name, c.slug) AS category_name,
                       ' . $this->translationSelect('pt', 'name') . ',
                       ' . $this->translationSelect('pt', 'short_description') . ',
                       ' . $this->translationSelect('pt', 'description') . ',
                       COALESCE(pt_loc.language_code, pt_fb.language_code) AS resolved_language_code
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
                INNER JOIN units u ON u.id = p.unit_id AND u.deleted_at IS NULL
                LEFT JOIN brands b ON b.id = p.brand_id AND b.deleted_at IS NULL
                LEFT JOIN product_families pf ON pf.id = p.family_id AND pf.deleted_at IS NULL
                ' . $this->translationJoin('p', 'id', 'product_translations', 'pt', 'product_id') . '
                ' . $this->translationJoin('c', 'id', 'category_translations', 'ct', 'category_id');
    }
}
