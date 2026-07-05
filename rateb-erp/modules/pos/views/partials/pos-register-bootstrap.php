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

        $invModel = new \Rateb\App\Models\Inventory();
        $invRows = $invModel->all(500, 0, [], '');
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
            $categoryId = (int) ($row['category_id'] ?? 0);
            $posBootstrap['productIndex'][(string) $id] = $categoryId;
            $doc = trim((string) ($row['document_path'] ?? ''));
            if ($doc !== '') {
                if (preg_match('#^https?://#i', $doc)) {
                    $posBootstrap['productImages'][(string) $id] = $doc;
                } elseif (str_starts_with($doc, '/')) {
                    $posBootstrap['productImages'][(string) $id] = $doc;
                } else {
                    $posBootstrap['productImages'][(string) $id] = rateb_asset(ltrim($doc, '/'));
                }
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
                'availability' => [
                    'on_hand' => $onHand,
                    'available' => max(0, $onHand),
                    'can_add' => $onHand > 0,
                ],
            ];
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
