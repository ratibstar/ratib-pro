<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Commerce;

use Ratib\InfrastructureMarketplace\Catalog\CatalogRepository;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;

/**
 * Canonical read/mapping between orders, catalog SKU, commerce plans/products, and provisioning hints.
 * Additive only — never mutates orders or catalog.
 */
final class OrderCommerceMapper
{
    public function __construct(
        private \PDO $pdo,
        private CatalogRepository $catalog,
        private PlanRepository $plans,
        private ProductRepository $products
    ) {
    }

    /**
     * @param array<string, mixed> $order ratib_infra_orders row
     * @return array<string, mixed>|null plan row or null
     */
    public function mapOrderToPlan(array $order, TenantContext $tenant): ?array
    {
        $sku = trim((string) ($order['sku'] ?? ''));
        if ($sku === '') {
            return null;
        }
        $plan = $this->plans->findFirstByPlanCode($sku);
        if ($plan !== null) {
            return $plan;
        }
        $visible = $this->catalog->listVisibleForTenant($tenant->tenantId());
        foreach ($visible as $row) {
            if (trim((string) ($row['sku'] ?? '')) === $sku) {
                $pc = (string) ($row['product_code'] ?? '');
                if ($pc !== '') {
                    $product = $this->products->findByProductCode($pc);
                    if (is_array($product) && isset($product['id'])) {
                        $byProduct = $this->plans->findByProductAndPlanCode((int) $product['id'], $sku);
                        if ($byProduct !== null) {
                            return $byProduct;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>|null product row
     */
    public function mapOrderToProduct(array $order, ?array $plan, TenantContext $tenant): ?array
    {
        if (is_array($plan) && isset($plan['product_id'])) {
            $p = $this->products->findById((int) $plan['product_id']);
            if ($p !== null) {
                return $p;
            }
        }
        $sku = trim((string) ($order['sku'] ?? ''));
        $visible = $this->catalog->listVisibleForTenant($tenant->tenantId());
        foreach ($visible as $row) {
            if (trim((string) ($row['sku'] ?? '')) !== $sku) {
                continue;
            }
            $pc = (string) ($row['product_code'] ?? '');
            if ($pc !== '') {
                return $this->products->findByProductCode($pc);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed>|null $plan
     * @param array<string, mixed>|null $product
     * @return array<string, mixed> provisioning intent metadata (not a job)
     */
    public function mapOrderToProvisioningIntent(array $order, ?array $plan, ?array $product): array
    {
        $sku = (string) ($order['sku'] ?? '');
        $profile = is_array($plan) ? (string) ($plan['provisioning_profile'] ?? '') : '';
        $capabilities = [];
        $visible = $this->catalog->listVisibleForTenant(isset($order['tenant_id']) ? (int) $order['tenant_id'] : null);
        foreach ($visible as $row) {
            if (trim((string) ($row['sku'] ?? '')) === trim($sku)) {
                $st = (string) (($row['service_type'] ?? '') ?: '');
                if ($st !== '') {
                    $capabilities[] = 'service_type:' . strtolower($st);
                }
                break;
            }
        }
        if ($profile !== '') {
            $capabilities[] = 'profile:' . $profile;
        }
        if (is_array($product) && isset($product['product_type'])) {
            $capabilities[] = 'product_type:' . strtolower((string) $product['product_type']);
        }

        return [
            'order_public_id' => (string) ($order['public_id'] ?? ''),
            'sku' => $sku,
            'plan_id' => is_array($plan) ? ($plan['id'] ?? null) : null,
            'product_id' => is_array($product) ? ($product['id'] ?? null) : null,
            'requested_capabilities' => array_values(array_unique($capabilities)),
        ];
    }

    /**
     * @return array{compatible: bool, warnings: list<string>, normalized_sku: string}
     */
    public function resolveSkuCompatibility(string $sku, TenantContext $tenant): array
    {
        $warnings = [];
        $sku = trim($sku);
        if ($sku === '') {
            return ['compatible' => false, 'warnings' => ['empty_sku'], 'normalized_sku' => ''];
        }
        $plan = $this->plans->findFirstByPlanCode($sku);
        if ($plan !== null) {
            $dup = $this->detectDuplicatePlanCodesGlobally($sku);
            if ($dup > 1) {
                $warnings[] = 'Ambiguous plan_code match: ' . $dup . ' rows share plan_code; mapper uses lowest id.';
            }

            return ['compatible' => true, 'warnings' => $warnings, 'normalized_sku' => $sku];
        }
        $visible = $this->catalog->listVisibleForTenant($tenant->tenantId());
        foreach ($visible as $row) {
            if (trim((string) ($row['sku'] ?? '')) === $sku) {
                return ['compatible' => true, 'warnings' => $warnings, 'normalized_sku' => $sku];
            }
        }
        $warnings[] = 'SKU not found in commerce plans or visible catalog for tenant.';

        return ['compatible' => false, 'warnings' => $warnings, 'normalized_sku' => $sku];
    }

    /**
     * @return list<string>
     */
    public function detectLegacyMappings(array $order, ?array $plan): array
    {
        $notes = [];
        $sku = trim((string) ($order['sku'] ?? ''));
        if ($sku === '' || $plan === null) {
            $notes[] = 'legacy_path: order sku not bound to ratib_infra_plans (catalog-only or pre-Phase2).';
        }
        if ($plan !== null && strtoupper(trim((string) ($plan['plan_code'] ?? ''))) !== strtoupper($sku)) {
            $notes[] = 'legacy_path: plan_code differs from order sku (mapping table recommended).';
        }
        $payload = json_decode((string) ($order['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $notes[] = 'legacy_path: payload_json not JSON object.';
        }

        return $notes;
    }

    /**
     * @return list<string> non-fatal validation warnings
     */
    public function validateCommerceBinding(array $order, ?array $plan, ?array $product): array
    {
        $w = [];
        if ($plan === null) {
            $w[] = 'No commerce plan binding for order; execution may rely on catalog-only semantics.';
        } else {
            $cs = strtoupper(trim((string) ($plan['commerce_state'] ?? '')));
            if ($cs === 'SUSPENDED' || $cs === 'CANCELLED' || $cs === 'EXPIRED') {
                $w[] = 'Plan commerce_state is ' . $cs . ' — activation may be inappropriate.';
            }
        }
        if (is_array($product) && empty($product['active'])) {
            $w[] = 'Product row marked inactive.';
        }

        return $w;
    }

    private function detectDuplicatePlanCodesGlobally(string $planCode): int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ratib_infra_plans WHERE plan_code = :c');
            $stmt->execute(['c' => $planCode]);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
