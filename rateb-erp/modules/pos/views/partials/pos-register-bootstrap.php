<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var string $csrf */

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
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);

        $isAr = function_exists('rateb_is_rtl') && rateb_is_rtl();
        $catModel = new \Rateb\App\Models\ProductCategory();
        $catRows = $catModel->all(300, 0, ['is_active' => 1], '');
        foreach ($catRows as $row) {
            if (array_key_exists('is_visible', $row) && !(int) ($row['is_visible'] ?? 1)) {
                continue;
            }
            $posBootstrap['categories'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => $isAr && trim((string) ($row['name_ar'] ?? '')) !== ''
                    ? (string) $row['name_ar']
                    : (string) ($row['name'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }
        usort($posBootstrap['categories'], static function (array $a, array $b): int {
            $cmp = ($a['sort_order'] <=> $b['sort_order']);
            return $cmp !== 0 ? $cmp : strcasecmp($a['name'], $b['name']);
        });

        $inventoryImageUrls = [];
        $pathToDocView = [];
        try {
            $db = \Rateb\App\Core\Database::connection();
            $stmt = $db->prepare(
                'SELECT entity_id, id, file_path FROM rateb_documents
                 WHERE company_id = :cid AND entity_type = :et
                 AND mime_type LIKE :mime
                 ORDER BY id DESC'
            );
            $stmt->execute(['cid' => $companyId, 'et' => 'inventory', 'mime' => 'image/%']);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $docRow) {
                $eid = (int) ($docRow['entity_id'] ?? 0);
                $docId = (int) ($docRow['id'] ?? 0);
                $filePath = (string) ($docRow['file_path'] ?? '');
                if ($docId > 0 && $filePath !== '') {
                    $pathToDocView[$filePath] = rateb_url('documents/view/' . $docId);
                }
                if ($eid > 0 && !isset($inventoryImageUrls[(string) $eid])) {
                    $inventoryImageUrls[(string) $eid] = rateb_url('documents/view/' . $docId);
                }
            }
        } catch (\Throwable $e) {
            $inventoryImageUrls = [];
            $pathToDocView = [];
        }

        $resolveImageUrl = static function (int $id, string $doc) use ($inventoryImageUrls, $pathToDocView): string {
            $key = (string) $id;
            if (isset($inventoryImageUrls[$key])) {
                return $inventoryImageUrls[$key];
            }
            if ($doc === '') {
                return '';
            }
            if (preg_match('#^https?://#i', $doc)) {
                return $doc;
            }
            if (str_starts_with($doc, '/')) {
                return $doc;
            }
            if (isset($pathToDocView[$doc])) {
                return $pathToDocView[$doc];
            }
            if (str_starts_with($doc, 'uploads/')) {
                return '';
            }
            return rateb_asset(ltrim($doc, '/'));
        };

        $invModel = new \Rateb\App\Models\Inventory();
        $scopeWarehouseId = (int) ($context['session']['warehouse_id'] ?? 0);
        if ($scopeWarehouseId < 1) {
            $scopeWarehouseId = (int) ($context['terminal']['warehouse_id'] ?? 0);
        }
        $scopeBranchId = (int) ($context['session']['branch_id'] ?? 0);
        $invFilters = [];
        if ($scopeWarehouseId > 0) {
            $invFilters['warehouse_id'] = $scopeWarehouseId;
        }
        $invRows = $invModel->all(500, 0, $invFilters, '');
        $usedWarehouseFallback = false;
        if ($invRows === [] && $scopeWarehouseId > 0) {
            $invRows = $invModel->all(500, 0, [], '');
            $usedWarehouseFallback = true;
        }
        $sellPrices = null;
        try {
            $sellPrices = new \Rateb\App\Pos\Services\PosSellPriceService();
        } catch (\Throwable $e) {
            $sellPrices = null;
        }
        foreach ($invRows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if ($scopeBranchId > 0) {
                $rowBranch = (int) ($row['branch_id'] ?? 0);
                if ($rowBranch > 0 && $rowBranch !== $scopeBranchId) {
                    continue;
                }
            }
            if (!$usedWarehouseFallback && $scopeWarehouseId > 0) {
                $rowWh = (int) ($row['warehouse_id'] ?? 0);
                if ($rowWh > 0 && $rowWh !== $scopeWarehouseId) {
                    continue;
                }
            }
            $categoryId = (int) ($row['category_id'] ?? 0);
            $posBootstrap['productIndex'][(string) $id] = $categoryId;
            $doc = trim((string) ($row['document_path'] ?? ''));
            $imgUrl = $resolveImageUrl($id, $doc);
            if ($imgUrl !== '') {
                $posBootstrap['productImages'][(string) $id] = $imgUrl;
            }

            $onHand = (float) ($row['quantity'] ?? 0);
            $unitPrice = 0.0;
            if ($sellPrices !== null) {
                try {
                    $branchId = (int) ($row['branch_id'] ?? 0);
                    $resolved = $sellPrices->resolveLine(
                        ['product_id' => $id, 'quantity' => 1],
                        $companyId,
                        $branchId > 0 ? $branchId : 0,
                        null
                    );
                    $unitPrice = (float) ($resolved['unit_price'] ?? 0);
                } catch (\Throwable $e) {
                    $unitPrice = 0.0;
                }
            }

            $posBootstrap['catalogSeed'][] = [
                'id' => $id,
                'item_code' => (string) ($row['item_code'] ?? ''),
                'item_name' => (string) ($row['item_name'] ?? ''),
                'unit_price' => $unitPrice,
                'category_id' => $categoryId,
                'image_url' => $imgUrl,
                'availability' => [
                    'on_hand' => $onHand,
                    'available' => max(0, $onHand),
                    'can_add' => $onHand > 0,
                ],
            ];
        }

        // Fallback demo catalog for full end-to-end POS testing.
        if ($posBootstrap['catalogSeed'] === []) {
            $demoCategoryIds = [];
            foreach ($posBootstrap['categories'] as $cat) {
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

            $branchId = (int) ($context['session']['branch_id'] ?? 0);
            if ($branchId < 1) {
                $branchId = (int) ($context['shift']['branch_id'] ?? 0);
            }
            $warehouseId = (int) ($context['session']['warehouse_id'] ?? 0);
            if ($warehouseId < 1) {
                $warehouseId = (int) ($context['terminal']['warehouse_id'] ?? 0);
            }
            if ($warehouseId < 1) {
                try {
                    $db = \Rateb\App\Core\Database::connection();
                    $wStmt = $db->prepare('SELECT id, branch_id FROM rateb_warehouses WHERE company_id = :cid ORDER BY id ASC LIMIT 1');
                    $wStmt->execute(['cid' => $companyId]);
                    $wRow = $wStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                    if ($wRow) {
                        $warehouseId = (int) ($wRow['id'] ?? 0);
                        if ($branchId < 1) {
                            $branchId = (int) ($wRow['branch_id'] ?? 0);
                        }
                    }
                } catch (\Throwable $e) {
                    // keep fallback values
                }
            }

            $invModelForSeed = new \Rateb\App\Models\Inventory();
            foreach ($demoProducts as $idx => $demo) {
                $catId = $demoCategoryIds[$idx % max(1, count($demoCategoryIds))] ?? $defaultCategoryId;
                $itemCode = (string) $demo['item_code'];
                $pid = 0;
                try {
                    $existing = $invModelForSeed->queryOne(
                        'SELECT id FROM rateb_inventory WHERE company_id = :cid AND item_code = :code LIMIT 1',
                        ['cid' => $companyId, 'code' => $itemCode]
                    );
                    if ($existing) {
                        $pid = (int) ($existing['id'] ?? 0);
                    } elseif ($warehouseId > 0) {
                        $pid = $invModelForSeed->create([
                            'warehouse_id' => $warehouseId,
                            'branch_id' => $branchId > 0 ? $branchId : null,
                            'item_code' => $itemCode,
                            'item_name' => (string) $demo['item_name'],
                            'sku' => $itemCode,
                            'barcode' => $itemCode,
                            'category_id' => (int) $catId,
                            'quantity' => 999,
                            'unit' => 'pcs',
                            'unit_cost' => (float) ($demo['unit_price'] ?? 0),
                            'status' => 'active',
                            'notes' => 'Auto-seeded POS demo item',
                        ]);
                    }
                } catch (\Throwable $e) {
                    $pid = 0;
                }
                if ($pid < 1) {
                    $pid = 990000 + $idx + 1;
                }
                $posBootstrap['productIndex'][(string) $pid] = (int) $catId;
                $posBootstrap['catalogSeed'][] = [
                    'id' => $pid,
                    'item_code' => $itemCode,
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
        }

        $posBootstrap['shiftTerminals'] = (new \Rateb\App\Pos\Services\PosFormLookupService())
            ->activeTerminals($companyId);
    }
} catch (\Throwable $e) {
    // UI bootstrap only — register still works without embedded catalog meta.
}

$terminalId = (int) (($context['terminal']['id'] ?? 0) ?: ($context['session']['terminal_id'] ?? 0));

echo json_encode([
    'categories' => $posBootstrap['categories'],
    'productIndex' => $posBootstrap['productIndex'],
    'productImages' => $posBootstrap['productImages'],
    'catalogSeed' => $posBootstrap['catalogSeed'],
    'shiftTerminals' => $posBootstrap['shiftTerminals'],
    'shiftOpenUrl' => $posBootstrap['shiftOpenUrl'],
    'registerUrl' => $posBootstrap['registerUrl'],
    'defaultTerminalId' => $terminalId,
    'csrf' => $csrf ?? '',
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
