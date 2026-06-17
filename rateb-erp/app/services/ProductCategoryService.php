<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Inventory;
use Rateb\App\Models\ProductCategory;

final class ProductCategoryService
{
    /** @return array{total:int,active:int,inactive:int,visible:int,hidden:int} */
    public function stats(int $companyId): array
    {
        if ($companyId < 1) {
            return ['total' => 0, 'active' => 0, 'inactive' => 0, 'visible' => 0, 'hidden' => 0];
        }
        $row = (new ProductCategory())->queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
                    SUM(CASE WHEN is_visible = 1 THEN 1 ELSE 0 END) AS visible,
                    SUM(CASE WHEN is_visible = 0 THEN 1 ELSE 0 END) AS hidden
             FROM rateb_product_categories WHERE company_id = :cid",
            ['cid' => $companyId]
        );
        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
            'visible' => (int) ($row['visible'] ?? 0),
            'hidden' => (int) ($row['hidden'] ?? 0),
        ];
    }

    /** @return array<int, int> */
    public function productCounts(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $rows = (new Inventory())->query(
            "SELECT category_id, COUNT(*) AS c
             FROM rateb_inventory
             WHERE company_id = :cid AND category_id IS NOT NULL AND category_id > 0
             GROUP BY category_id",
            ['cid' => $companyId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) ($row['category_id'] ?? 0)] = (int) ($row['c'] ?? 0);
        }
        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    public function mostUsed(int $companyId, int $limit = 5): array
    {
        if ($companyId < 1) {
            return [];
        }
        return (new Inventory())->query(
            "SELECT pc.id, pc.name, pc.name_ar, pc.code, COUNT(i.id) AS product_count
             FROM rateb_product_categories pc
             LEFT JOIN rateb_inventory i ON i.category_id = pc.id AND i.company_id = pc.company_id
             WHERE pc.company_id = :cid
             GROUP BY pc.id, pc.name, pc.name_ar, pc.code
             ORDER BY product_count DESC, pc.name ASC
             LIMIT " . max(1, min(20, $limit)),
            ['cid' => $companyId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function tree(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $rows = (new ProductCategory())->query(
            "SELECT id, parent_id, name, name_ar, code, is_active, is_visible, sort_order
             FROM rateb_product_categories
             WHERE company_id = :cid
             ORDER BY sort_order ASC, name ASC",
            ['cid' => $companyId]
        );
        return $this->buildTree($rows);
    }

    /** @return list<array{id:int,label:string}> */
    public function breadcrumb(int $categoryId): array
    {
        $out = [];
        $seen = [];
        $current = $categoryId;
        while ($current > 0 && !isset($seen[$current])) {
            $seen[$current] = true;
            $row = (new ProductCategory())->find($current);
            if (!$row) {
                break;
            }
            $label = rateb_locale() === 'ar' && !empty($row['name_ar']) ? (string) $row['name_ar'] : (string) ($row['name'] ?? '');
            array_unshift($out, ['id' => $current, 'label' => $label]);
            $current = (int) ($row['parent_id'] ?? 0);
        }
        return $out;
    }

    public function deleteBlockedReason(int $categoryId): ?string
    {
        if ($categoryId < 1) {
            return __('invalid_request');
        }
        $cat = (new ProductCategory())->find($categoryId);
        if (!$cat) {
            return __('invalid_request');
        }
        $child = (new ProductCategory())->queryOne(
            'SELECT id FROM rateb_product_categories WHERE parent_id = :pid LIMIT 1',
            ['pid' => $categoryId]
        );
        if ($child) {
            return __('category_has_children');
        }
        $product = (new Inventory())->queryOne(
            'SELECT id FROM rateb_inventory WHERE category_id = :cid LIMIT 1',
            ['cid' => $categoryId]
        );
        if ($product) {
            return __('category_has_products');
        }
        return null;
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function enrichRows(array $rows, int $companyId): array
    {
        $counts = $this->productCounts($companyId);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) ($row['id'] ?? 0)] = $row;
        }
        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $row['product_count'] = $counts[$id] ?? 0;
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId > 0 && isset($byId[$parentId])) {
                $parent = $byId[$parentId];
                $row['parent_label'] = rateb_locale() === 'ar' && !empty($parent['name_ar'])
                    ? (string) $parent['name_ar']
                    : (string) ($parent['name'] ?? '');
            } else {
                $row['parent_label'] = '—';
            }
        }
        unset($row);
        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function productsByCategoryReport(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        return (new Inventory())->query(
            "SELECT pc.id AS category_id, pc.code AS category_code,
                    COALESCE(NULLIF(pc.name_ar, ''), pc.name) AS category_name,
                    COUNT(i.id) AS product_count,
                    COALESCE(SUM(i.quantity * COALESCE(i.unit_cost, 0)), 0) AS stock_value
             FROM rateb_product_categories pc
             LEFT JOIN rateb_inventory i ON i.category_id = pc.id AND i.company_id = pc.company_id
             WHERE pc.company_id = :cid
             GROUP BY pc.id, pc.code, pc.name, pc.name_ar
             ORDER BY product_count DESC, category_name ASC",
            ['cid' => $companyId]
        );
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return list<array<string, mixed>>
     */
    private function buildTree(array $rows, ?int $parentId = null, int $depth = 0): array
    {
        $out = [];
        foreach ($rows as $row) {
            $pid = $row['parent_id'] ?? null;
            $pid = ($pid === null || $pid === '' || (int) $pid < 1) ? null : (int) $pid;
            if ($pid !== $parentId) {
                continue;
            }
            $label = rateb_locale() === 'ar' && !empty($row['name_ar']) ? (string) $row['name_ar'] : (string) ($row['name'] ?? '');
            $node = $row;
            $node['depth'] = $depth;
            $node['label'] = $label;
            $node['children'] = $this->buildTree($rows, (int) ($row['id'] ?? 0), $depth + 1);
            $out[] = $node;
        }
        return $out;
    }
}
