<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Catalog;

/**
 * Read-only bridge from legacy catalog rows to commerce-shaped views.
 * Does not INSERT into ratib_infra_products / plans (non-destructive).
 */
final class CatalogCommerceBridge
{
    public function __construct(
        private CatalogRepository $catalog
    ) {
    }

    /**
     * @param array<string, mixed> $catalogRow from ratib_infra_catalog_items
     * @return array<string, mixed> synthetic product (not persisted)
     */
    public function mapCatalogItemToProduct(array $catalogRow): array
    {
        $sku = (string) ($catalogRow['sku'] ?? '');
        $title = (string) ($catalogRow['title'] ?? $sku);
        $tenantId = isset($catalogRow['tenant_id']) ? (int) $catalogRow['tenant_id'] : null;

        return [
            '_source' => 'ratib_infra_catalog_items',
            '_synthetic' => true,
            'product_code' => 'legacy_catalog:' . $sku,
            'product_type' => $this->inferProductType($catalogRow),
            'display_name' => $title,
            'description' => null,
            'active' => !empty($catalogRow['is_active']),
            'visibility_state' => 'PUBLIC',
            'lifecycle_state' => 'ACTIVE',
            'provider_binding' => null,
            'tenant_scope_mode' => $tenantId !== null && $tenantId > 0 ? 'TENANT' : 'GLOBAL',
            'agency_scope_mode' => 'NONE',
            'feature_flags_json' => null,
            'metadata_json' => [
                'legacy_catalog_row' => [
                    'sku' => $sku,
                    'tenant_id' => $tenantId,
                    'billing_code' => $catalogRow['billing_code'] ?? null,
                    'raw_metadata' => $catalogRow['metadata_json'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $catalogRow
     * @return array<string, mixed> synthetic plan (not persisted)
     */
    public function mapCatalogItemToPlan(array $catalogRow): array
    {
        $product = $this->mapCatalogItemToProduct($catalogRow);
        $sku = (string) ($catalogRow['sku'] ?? '');

        return [
            '_source' => 'ratib_infra_catalog_items',
            '_synthetic' => true,
            'plan_code' => 'legacy_catalog_plan:' . $sku,
            'display_name' => (string) ($catalogRow['title'] ?? $sku),
            'billing_cycle' => 'one_time',
            'currency' => 'USD',
            'base_price' => 0.0,
            'setup_fee' => 0.0,
            'commerce_state' => 'ACTIVE',
            'provisioning_profile' => 'legacy_catalog_item',
            'metadata_json' => [
                'derived_from_product_code' => $product['product_code'],
            ],
            '_resolved_product' => $product,
        ];
    }

    /**
     * Resolve by SKU for display / admin tools (read-only).
     *
     * @return array<string, mixed>|null
     */
    public function resolveLegacyProduct(string $sku, ?int $tenantId): ?array
    {
        $rows = $this->catalog->listVisibleForTenant($tenantId);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['sku'] ?? '') === $sku) {
                return $this->mapCatalogItemToProduct($row);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function compatibilityView(?int $tenantId): array
    {
        $items = $this->catalog->listVisibleForTenant($tenantId);
        $mapped = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped[] = [
                'legacy' => $row,
                'commerce_product_view' => $this->mapCatalogItemToProduct($row),
                'commerce_plan_view' => $this->mapCatalogItemToPlan($row),
            ];
        }
        return [
            'tenant_id' => $tenantId,
            'count' => count($mapped),
            'items' => $mapped,
            'notes' => [
                'Views are synthetic until rows are optionally mirrored into ratib_infra_products / ratib_infra_plans.',
                'Never delete or rewrite ratib_infra_catalog_items from this bridge.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $catalogRow
     */
    private function inferProductType(array $catalogRow): string
    {
        $meta = $catalogRow['metadata_json'] ?? null;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (is_array($decoded) && isset($decoded['product_type'])) {
                return (string) $decoded['product_type'];
            }
        }
        if (is_array($meta) && isset($meta['product_type'])) {
            return (string) $meta['product_type'];
        }
        $sku = strtolower((string) ($catalogRow['sku'] ?? ''));
        if (str_contains($sku, 'domain')) {
            return 'domains';
        }
        if (str_contains($sku, 'ssl')) {
            return 'ssl';
        }
        if (str_contains($sku, 'host')) {
            return 'hosting';
        }

        return 'other';
    }
}
