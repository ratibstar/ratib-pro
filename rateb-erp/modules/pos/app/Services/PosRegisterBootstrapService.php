<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\ProductCategory;

/** Fast POS register bootstrap — light shell payload + catalog API payload. */
final class PosRegisterBootstrapService
{
    /**
     * Minimal data for first paint (shift gate, categories, terminals).
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function lightPayload(array $context, string $csrf = ''): array
    {
        $posBootstrap = [
            'categories' => [],
            'productIndex' => [],
            'productImages' => [],
            'catalogSeed' => [],
            'shiftTerminals' => [],
            'shiftOpenUrl' => rateb_app_url('pos/shifts/open'),
            'registerUrl' => rateb_app_url('pos/register'),
        ];

        try {
            $companyId = (int) ($context['company_id'] ?? 0);
            if ($companyId < 1 && function_exists('rateb_require_ops_company')) {
                $companyId = (int) rateb_require_ops_company();
            }
            if ($companyId > 0) {
                TenantContext::setCompanyId($companyId);
                $posBootstrap['categories'] = $this->loadCategories();
                $posBootstrap['shiftTerminals'] = (new PosFormLookupService())->activeTerminals($companyId);
            }
        } catch (\Throwable $e) {
            // UI bootstrap only
        }

        $terminalId = (int) (($context['terminal']['id'] ?? 0) ?: ($context['session']['terminal_id'] ?? 0));

        return [
            'categories' => $posBootstrap['categories'],
            'productIndex' => $posBootstrap['productIndex'],
            'productImages' => $posBootstrap['productImages'],
            'catalogSeed' => $posBootstrap['catalogSeed'],
            'shiftTerminals' => $posBootstrap['shiftTerminals'],
            'shiftOpenUrl' => $posBootstrap['shiftOpenUrl'],
            'registerUrl' => $posBootstrap['registerUrl'],
            'defaultTerminalId' => $terminalId,
            'csrf' => $csrf,
        ];
    }

    /**
     * Full product catalog for async client load.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function catalogPayload(array $context): array
    {
        $out = [
            'productIndex' => [],
            'productImages' => [],
            'catalogSeed' => [],
        ];

        try {
            $companyId = (int) ($context['company_id'] ?? 0);
            if ($companyId < 1 && function_exists('rateb_require_ops_company')) {
                $companyId = (int) rateb_require_ops_company();
            }
            if ($companyId < 1) {
                return $out;
            }

            TenantContext::setCompanyId($companyId);
            $scopeWarehouseId = (int) ($context['session']['warehouse_id'] ?? 0);
            if ($scopeWarehouseId < 1) {
                $scopeWarehouseId = (int) ($context['terminal']['warehouse_id'] ?? 0);
            }
            $scopeBranchId = (int) ($context['session']['branch_id'] ?? 0);

            $invRows = $this->loadInventoryRows($companyId, $scopeWarehouseId, $scopeBranchId);
            $inventoryIds = [];
            foreach ($invRows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $inventoryIds[] = $id;
                }
            }
            $imageSvc = new \Rateb\App\Services\InventoryImageService();
            $imageDocIds = $imageSvc->inventoryIdsWithImageDocs($companyId, $inventoryIds);
            foreach ($invRows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $categoryId = (int) ($row['category_id'] ?? 0);
                $out['productIndex'][(string) $id] = $categoryId;
                $imgUrl = $imageSvc->resolveCatalogImageUrl($id, $row, isset($imageDocIds[$id]));
                if ($imgUrl !== '') {
                    $out['productImages'][(string) $id] = $imgUrl;
                }
                $onHand = (float) ($row['quantity'] ?? 0);
                $out['catalogSeed'][] = [
                    'id' => $id,
                    'item_code' => (string) ($row['item_code'] ?? ''),
                    'item_name' => (string) ($row['item_name'] ?? ''),
                    'unit_price' => $this->unitPriceFromRow($row),
                    'category_id' => $categoryId,
                    'image_url' => $imgUrl,
                    'availability' => [
                        'on_hand' => $onHand,
                        'available' => max(0, $onHand),
                        'can_add' => $onHand > 0,
                    ],
                ];
            }

            if ($out['catalogSeed'] === []) {
                $out = array_merge($out, $this->demoCatalog($context, $this->loadCategories()));
            }
        } catch (\Throwable $e) {
            // catalog optional
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function loadCategories(): array
    {
        $isAr = function_exists('rateb_is_rtl') && rateb_is_rtl();
        $catModel = new ProductCategory();
        $catRows = $catModel->all(300, 0, ['is_active' => 1], '');
        $categories = [];
        foreach ($catRows as $row) {
            if (array_key_exists('is_visible', $row) && !(int) ($row['is_visible'] ?? 1)) {
                continue;
            }
            $categories[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => $isAr && trim((string) ($row['name_ar'] ?? '')) !== ''
                    ? (string) $row['name_ar']
                    : (string) ($row['name'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }
        usort($categories, static function (array $a, array $b): int {
            $cmp = ($a['sort_order'] <=> $b['sort_order']);
            return $cmp !== 0 ? $cmp : strcasecmp($a['name'], $b['name']);
        });

        return $categories;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadInventoryRows(int $companyId, int $scopeWarehouseId, int $scopeBranchId): array
    {
        $db = Database::connection();
        $sql = 'SELECT id, item_code, item_name, sell_price, unit_cost, category_id, quantity,
                       warehouse_id, branch_id, document_path
                FROM rateb_inventory
                WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($scopeWarehouseId > 0) {
            $sql .= ' AND warehouse_id = :wh';
            $params['wh'] = $scopeWarehouseId;
        }
        if ($scopeBranchId > 0) {
            $sql .= ' AND (branch_id IS NULL OR branch_id = 0 OR branch_id = :bid)';
            $params['bid'] = $scopeBranchId;
        }
        $sql .= ' ORDER BY item_name ASC LIMIT 500';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if ($rows === [] && $scopeWarehouseId > 0) {
            $invModel = new Inventory();
            $rows = $invModel->all(500, 0, [], '');
            $filtered = [];
            foreach ($rows as $row) {
                if ($scopeBranchId > 0) {
                    $rowBranch = (int) ($row['branch_id'] ?? 0);
                    if ($rowBranch > 0 && $rowBranch !== $scopeBranchId) {
                        continue;
                    }
                }
                $filtered[] = $row;
            }
            return $filtered;
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function unitPriceFromRow(array $row): float
    {
        $sell = $row['sell_price'] ?? null;
        if ($sell !== null && $sell !== '' && (float) $sell > 0) {
            return round((float) $sell, 2);
        }

        return round((float) ($row['unit_cost'] ?? 0), 2);
    }

    /**
     * In-memory demo items only — no DB writes on page load.
     *
     * @param array<string, mixed> $context
     * @param list<array<string, mixed>> $categories
     * @return array{productIndex: array<string, int>, productImages: array<string, string>, catalogSeed: list<array<string, mixed>>}
     */
    private function demoCatalog(array $context, array $categories): array
    {
        $demoCategoryIds = [];
        foreach ($categories as $cat) {
            $cid = (int) ($cat['id'] ?? 0);
            if ($cid > 0) {
                $demoCategoryIds[] = $cid;
            }
        }
        $defaultCategoryId = $demoCategoryIds[0] ?? 0;
        $demoProducts = [
            ['item_code' => 'DEMO-ESP', 'item_name' => 'Espresso (Demo)', 'unit_price' => 12.00],
            ['item_code' => 'DEMO-LAT', 'item_name' => 'Latte (Demo)', 'unit_price' => 16.00],
            ['item_code' => 'DEMO-CRO', 'item_name' => 'Croissant (Demo)', 'unit_price' => 9.50],
            ['item_code' => 'DEMO-SAN', 'item_name' => 'Chicken Sandwich (Demo)', 'unit_price' => 22.00],
            ['item_code' => 'DEMO-WAT', 'item_name' => 'Water 330ml (Demo)', 'unit_price' => 3.00],
        ];

        $productIndex = [];
        $catalogSeed = [];
        foreach ($demoProducts as $idx => $demo) {
            $catId = $demoCategoryIds[$idx % max(1, count($demoCategoryIds))] ?? $defaultCategoryId;
            $pid = 990000 + $idx + 1;
            $productIndex[(string) $pid] = (int) $catId;
            $catalogSeed[] = [
                'id' => $pid,
                'item_code' => (string) $demo['item_code'],
                'item_name' => (string) $demo['item_name'],
                'unit_price' => (float) $demo['unit_price'],
                'category_id' => (int) $catId,
                'image_url' => '',
                'availability' => [
                    'on_hand' => 999.0,
                    'available' => 999.0,
                    'can_add' => true,
                ],
            ];
        }

        return [
            'productIndex' => $productIndex,
            'productImages' => [],
            'catalogSeed' => $catalogSeed,
        ];
    }
}
