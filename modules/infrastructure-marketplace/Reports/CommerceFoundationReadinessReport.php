<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Reports;

use Ratib\InfrastructureMarketplace\Catalog\CatalogCommerceBridge;
use Ratib\InfrastructureMarketplace\Catalog\CatalogRepository;
use Ratib\InfrastructureMarketplace\Commerce\ProductRepository;
use Ratib\InfrastructureMarketplace\Infrastructure\SchemaHelpers;
use Ratib\InfrastructureMarketplace\Schema\SchemaAliasMap;
use Ratib\InfrastructureMarketplace\State\StateNamespaceRegistry;

/**
 * Validates Phase 2 readiness: files, schema presence, namespace sanity (warnings, not hard failures).
 *
 * CLI: `php modules/infrastructure-marketplace/Reports/CommerceFoundationReadinessReport.php`
 */
final class CommerceFoundationReadinessReport
{
    /**
     * @return array<string, mixed>
     */
    public static function build(?\PDO $pdo = null): array
    {
        $root = dirname(__DIR__);
        $files = [
            'Commerce/ProductRepository.php',
            'Commerce/PlanRepository.php',
            'Commerce/PlanFeatureRepository.php',
            'Commerce/PricingRepository.php',
            'Commerce/ProductLifecycleManager.php',
            'Catalog/CatalogCommerceBridge.php',
            'State/StateNamespaceRegistry.php',
            'Resources/ResourceRelationshipGraph.php',
            'Tenants/TenantResourceManager.php',
            'Schema/SchemaAliasMap.php',
            'Migrations/Phase2/001_commerce_foundation_tables.sql',
            'Migrations/Phase2/002_tenant_resources_overlay.sql',
        ];
        $fileOk = [];
        foreach ($files as $rel) {
            $fileOk[$rel] = is_file($root . '/' . $rel);
        }

        $tables = [
            'ratib_infra_products',
            'ratib_infra_plans',
            'ratib_infra_plan_features',
            'ratib_infra_pricing',
            'ratib_tenant_resources',
            'ratib_infra_catalog_items',
            'ratib_infra_provisioning_jobs',
        ];
        $tableStatus = [];
        foreach ($tables as $t) {
            if ($pdo === null) {
                $tableStatus[$t] = 'SKIPPED_NO_PDO';
            } else {
                try {
                    $tableStatus[$t] = SchemaHelpers::tableExists($pdo, $t) ? 'PRESENT' : 'MISSING';
                } catch (\Throwable $e) {
                    $tableStatus[$t] = 'ERROR:' . $e->getMessage();
                }
            }
        }

        $namespaceSamples = [
            'queue_RUNNING' => StateNamespaceRegistry::validateQueueState('RUNNING'),
            'commerce_ACTIVE' => StateNamespaceRegistry::validateCommerceState('ACTIVE'),
            'ownership_OWNED' => StateNamespaceRegistry::validateOwnershipState('OWNED'),
            'phase_DNS' => StateNamespaceRegistry::validateProvisioningPhase('DNS_SETUP'),
            'ambiguous_WAITING_EXTERNAL' => StateNamespaceRegistry::validateProvisioningPhase('WAITING_EXTERNAL'),
        ];

        $aliasWarnings = SchemaAliasMap::compatibilityWarningsFor('ratib_infra_catalog_item');

        $bridgeSample = 'SKIPPED_NO_PDO';
        if ($pdo !== null) {
            try {
                $repo = new CatalogRepository($pdo);
                $bridge = new CatalogCommerceBridge($repo);
                $view = $bridge->compatibilityView(null);
                $bridgeSample = ['count' => $view['count'] ?? 0, 'notes' => $view['notes'] ?? []];
            } catch (\Throwable $e) {
                $bridgeSample = 'ERROR:' . $e->getMessage();
            }
        }

        $commerceRepoProbe = 'SKIPPED_NO_PDO';
        if ($pdo !== null && ($tableStatus['ratib_infra_products'] ?? '') === 'PRESENT') {
            try {
                $pr = new ProductRepository($pdo);
                $commerceRepoProbe = ['product_rows' => count($pr->listActive())];
            } catch (\Throwable $e) {
                $commerceRepoProbe = 'ERROR:' . $e->getMessage();
            }
        }

        $compat = CommerceFoundationCompatibilityReport::build();

        return [
            'schema_version' => 'phase2-readiness-1.0',
            'generated_at' => gmdate('c'),
            '1_new_files' => $fileOk,
            '2_reused_systems' => $compat['reused_systems'],
            '3_extended_systems' => $compat['extended_systems'],
            '4_compatibility_risks' => $compat['compatibility_risks'],
            '5_migration_risks' => [
                'If 002 runs before 001, FK creation fails — enforce order.',
                'If products empty, FK inserts from tenant_resources still valid with NULL commerce ids.',
            ],
            '6_missing_future_dependencies' => [
                'Orders ↔ plans SKU linkage (additive FK or mapping table) — Phase 3.',
                'Client-dashboard snapshot widgets — later phase.',
                'Email / Websites / AI — explicitly excluded here.',
            ],
            '7_recommended_phase3_preparation' => [
                'Add optional ratib_infra_order_line_items referencing plan_id without breaking ratib_infra_orders payload_json.',
                'Introduce read API versioning only additively (new query params).',
            ],
            '8_architectural_hazards' => [
                'ratib_infra_services.lifecycle_state default QUEUED reads like queue vocabulary — treat as service lifecycle, not job queue.',
            ],
            '9_unresolved_legacy_conflicts' => $aliasWarnings,
            '10_rollback_safety' => [
                'Rollback: DROP TABLE ratib_tenant_resources, ratib_infra_pricing, ratib_infra_plan_features, ratib_infra_plans, ratib_infra_products in reverse dependency order only if no FK references from production data; prefer feature flags to disable commerce reads first.',
            ],
            'table_status' => $tableStatus,
            'namespace_sample_warnings' => $namespaceSamples,
            'catalog_bridge_sample' => $bridgeSample,
            'commerce_repository_probe' => $commerceRepoProbe,
        ];
    }

    public static function toJson(array $r): string
    {
        $j = json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $j !== false ? $j : '{}';
    }

    public static function toHtml(array $r): string
    {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Commerce foundation readiness</title>'
            . '<style>body{font-family:system-ui,sans-serif;margin:1.2rem;background:#0b1020;color:#e5e7eb}pre{background:#020617;padding:.75rem;border-radius:8px;overflow:auto}</style></head><body>'
            . '<h1>Commerce foundation readiness</h1><pre>' . htmlspecialchars(self::toJson($r), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre></body></html>';
    }
}

if (PHP_SAPI === 'cli') {
    $norm = static fn (string $p): string => strtolower(str_replace('\\', '/', $p));
    $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($script !== '' && $norm($script) === $norm(__FILE__)) {
        require_once dirname(__DIR__) . '/bootstrap.php';
        $pdo = null;
        try {
            $pdo = \Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory::createPdo();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Note: ' . $e->getMessage() . "\n");
        }
        $r = CommerceFoundationReadinessReport::build($pdo);
        $dir = __DIR__;
        file_put_contents($dir . '/commerce-foundation-readiness.json', CommerceFoundationReadinessReport::toJson($r));
        file_put_contents($dir . '/commerce-foundation-readiness.html', CommerceFoundationReadinessReport::toHtml($r));
        fwrite(STDOUT, "Wrote commerce-foundation-readiness.{json,html}\n");
    }
}
