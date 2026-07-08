<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\DuplicateReadRepositoryInterface;

final class MysqlDuplicateReadRepository extends BaseRepository implements DuplicateReadRepositoryInterface
{
    protected function table(): string
    {
        return 'duplicate_groups';
    }

    public function listGroups(?string $status, int $limit, int $offset): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $where = ['dg.deleted_at IS NULL'];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'dg.status = :status';
            $params['status'] = $status;
        }

        return $this->fetchAll(
            'SELECT dg.uuid, dg.group_key, dg.status, dg.match_rule_id, dg.resolved_by,
                    dg.resolved_at, dg.resolution_note, dg.created_at, dg.updated_at,
                    dr.code AS rule_code,
                    COUNT(dgp.id) AS product_count
             FROM duplicate_groups dg
             LEFT JOIN duplicate_rules dr ON dr.id = dg.match_rule_id AND dr.deleted_at IS NULL
             LEFT JOIN duplicate_group_products dgp ON dgp.duplicate_group_id = dg.id AND dgp.deleted_at IS NULL
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY dg.id, dg.uuid, dg.group_key, dg.status, dg.match_rule_id, dg.resolved_by,
                      dg.resolved_at, dg.resolution_note, dg.created_at, dg.updated_at, dr.code
             ORDER BY dg.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    public function findGroupByUuid(string $uuid): ?array
    {
        $group = $this->fetchOne(
            'SELECT dg.uuid, dg.group_key, dg.status, dg.match_rule_id, dg.resolved_by,
                    dg.resolved_at, dg.resolution_note, dg.created_at, dg.updated_at,
                    dr.code AS rule_code, dr.match_field, dr.match_type
             FROM duplicate_groups dg
             LEFT JOIN duplicate_rules dr ON dr.id = dg.match_rule_id AND dr.deleted_at IS NULL
             WHERE dg.uuid = :uuid AND dg.deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
        if ($group === null) {
            return null;
        }

        $group['products'] = $this->fetchAll(
            'SELECT dgp.uuid, p.uuid AS product_uuid, p.sku, p.primary_barcode,
                    dgp.match_score, dgp.is_primary, dgp.created_at
             FROM duplicate_group_products dgp
             INNER JOIN duplicate_groups dg ON dg.id = dgp.duplicate_group_id AND dg.deleted_at IS NULL
             INNER JOIN products p ON p.id = dgp.product_id AND p.deleted_at IS NULL
             WHERE dg.uuid = :uuid AND dgp.deleted_at IS NULL
             ORDER BY dgp.is_primary DESC, dgp.id ASC',
            ['uuid' => $uuid]
        );

        return $group;
    }

    public function listRules(): array
    {
        return $this->fetchAll(
            'SELECT uuid, code, match_field, match_type, threshold, is_active, priority, created_at
             FROM duplicate_rules
             WHERE deleted_at IS NULL
             ORDER BY priority DESC, id ASC'
        );
    }

    public function findSkuMatches(string $sku): array
    {
        return $this->fetchAll(
            'SELECT p.uuid, p.sku, p.primary_barcode, p.status, p.version_number
             FROM products p
             WHERE p.sku = :sku AND p.deleted_at IS NULL
             ORDER BY p.id ASC',
            ['sku' => $sku]
        );
    }

    public function findBarcodeMatches(string $barcode): array
    {
        return $this->fetchAll(
            'SELECT DISTINCT p.uuid, p.sku, p.primary_barcode, p.status, p.version_number
             FROM products p
             LEFT JOIN product_barcodes pb ON pb.product_id = p.id AND pb.deleted_at IS NULL
             WHERE p.deleted_at IS NULL
               AND (p.primary_barcode = :barcode OR pb.barcode = :barcode)
             ORDER BY p.id ASC',
            ['barcode' => $barcode]
        );
    }

    public function findSkuCollisionGroups(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $rows = $this->fetchAll(
            'SELECT p.sku, GROUP_CONCAT(p.id ORDER BY p.id ASC) AS product_ids
             FROM products p
             WHERE p.deleted_at IS NULL AND p.sku <> ""
             GROUP BY p.sku
             HAVING COUNT(*) > 1
             ORDER BY p.sku ASC
             LIMIT ' . $limit
        );

        return array_map(static function (array $row): array {
            $ids = array_map('intval', array_filter(explode(',', (string) ($row['product_ids'] ?? ''))));

            return ['sku' => (string) $row['sku'], 'product_ids' => $ids];
        }, $rows);
    }

    public function findBarcodeCollisionGroups(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $rows = $this->fetchAll(
            'SELECT barcode, GROUP_CONCAT(product_id ORDER BY product_id ASC) AS product_ids
             FROM (
                 SELECT p.primary_barcode AS barcode, p.id AS product_id
                 FROM products p
                 WHERE p.deleted_at IS NULL AND p.primary_barcode IS NOT NULL AND p.primary_barcode <> ""
                 UNION ALL
                 SELECT pb.barcode AS barcode, pb.product_id AS product_id
                 FROM product_barcodes pb
                 INNER JOIN products p ON p.id = pb.product_id AND p.deleted_at IS NULL
                 WHERE pb.deleted_at IS NULL AND pb.barcode <> ""
             ) barcodes
             GROUP BY barcode
             HAVING COUNT(*) > 1
             ORDER BY barcode ASC
             LIMIT ' . $limit
        );

        return array_map(static function (array $row): array {
            $ids = array_map('intval', array_filter(explode(',', (string) ($row['product_ids'] ?? ''))));

            return ['barcode' => (string) $row['barcode'], 'product_ids' => $ids];
        }, $rows);
    }
}
