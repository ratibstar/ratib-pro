<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Reports;

use RATEB\InfrastructureMarketplace\Commerce\OrderCommerceMapper;
use RATEB\InfrastructureMarketplace\Infrastructure\SchemaHelpers;
use RATEB\InfrastructureMarketplace\Lifecycle\LifecycleBindingCoordinator;
use RATEB\InfrastructureMarketplace\Resources\ResourceRelationshipGraph;
use RATEB\InfrastructureMarketplace\State\StateNamespaceRegistry;

/**
 * Phase 3 execution readiness — additive checks, warnings-first.
 *
 * CLI: `php modules/infrastructure-marketplace/Reports/CommerceExecutionReadinessReport.php`
 */
final class CommerceExecutionReadinessReport
{
    /**
     * @return array<string, mixed>
     */
    public static function build(?\PDO $pdo = null): array
    {
        $root = dirname(__DIR__);
        $files = [
            'Reports/CommerceExecutionAuditReport.php',
            'Commerce/OrderCommerceMapper.php',
            'Commerce/CommerceActivationEngine.php',
            'Provisioning/ProvisioningIntent.php',
            'Provisioning/ProvisioningIntentFactory.php',
            'Resources/ResourceIdentityManager.php',
            'Lifecycle/LifecycleBindingCoordinator.php',
            'Execution/ExecutionSafetyLayer.php',
            'Providers/ProviderExecutionBinder.php',
        ];
        $fileOk = [];
        foreach ($files as $rel) {
            $fileOk[$rel] = is_file($root . '/' . $rel);
        }

        $tables = [
            'rateb_infra_orders',
            'rateb_infra_plans',
            'rateb_infra_products',
            'rateb_tenant_resources',
            'rateb_infra_provisioning_jobs',
            'rateb_infra_audit_entries',
            'rateb_infra_provider_activations',
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
            'provider_HEALTHY' => StateNamespaceRegistry::validateProviderState('HEALTHY'),
            'phase_DNS' => StateNamespaceRegistry::validateProvisioningPhase('DNS_SETUP'),
        ];

        $graphProbe = self::graphProbe();
        $lifecycleProbe = self::lifecycleProbe();
        $mapperProbe = $pdo !== null ? self::mapperProbe($pdo) : 'SKIPPED_NO_PDO';

        $audit = CommerceExecutionAuditReport::build($pdo);

        return [
            'schema_version' => 'commerce-execution-readiness-1.0',
            'generated_at' => gmdate('c'),
            '1_new_files' => $fileOk,
            '2_order_mapping_integrity' => [
                'OrderCommerceMapper resolves sku → plan_code → rateb_infra_plans or visible catalog.',
                'tables' => [
                    'rateb_infra_orders' => $tableStatus['rateb_infra_orders'] ?? 'unknown',
                    'rateb_infra_plans' => $tableStatus['rateb_infra_plans'] ?? 'unknown',
                ],
            ],
            '3_provisioning_intent_integrity' => [
                'Intent is DTO + audit; not a replacement for rateb_infra_provisioning_jobs.',
                'files' => $fileOk['Provisioning/ProvisioningIntent.php'] ?? false,
            ],
            '4_lifecycle_synchronization' => $lifecycleProbe,
            '5_resource_identity_integrity' => [
                'ResourceIdentityManager generates UUID-style public ids; graph keys prefixed res:',
                'file' => $fileOk['Resources/ResourceIdentityManager.php'] ?? false,
            ],
            '6_duplicate_execution_risk' => [
                'Mitigated by ExecutionSafetyLayer + audit marker commerce_activation_completed.',
                'file' => $fileOk['Execution/ExecutionSafetyLayer.php'] ?? false,
            ],
            '7_provider_binding_readiness' => [
                'ProviderExecutionBinder uses ProviderRegistry + ProviderActivationRegistry.',
                'activations_table' => $tableStatus['rateb_infra_provider_activations'] ?? 'unknown',
            ],
            '8_ownership_consistency' => [
                'TenantResourceManager + rateb_tenant_resources overlay.',
                'tenant_resources_table' => $tableStatus['rateb_tenant_resources'] ?? 'unknown',
            ],
            '9_drift_detection_readiness' => [
                'LifecycleBindingCoordinator + ResourceRelationshipGraph activationTopologicalOrder().',
                'graph_probe' => $graphProbe,
            ],
            '10_rollback_safety' => [
                'Phase 3 is code-first; no destructive DDL. Disable CommerceActivationEngine callers; audit rows remain for forensics.',
                'Optional: remove tenant_resources rows created for commerce_activation metadata.',
            ],
            '11_execution_audit_summary' => $audit,
            'namespace_sample_warnings' => $namespaceSamples,
            'order_mapper_probe' => $mapperProbe,
            'table_status' => $tableStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function graphProbe(): array
    {
        $g = new ResourceRelationshipGraph();
        $g->addNode('d1', 'domain');
        $g->addNode('z1', 'dns_zone');
        $g->addActivationDependency('d1', 'z1');
        $order = $g->activationTopologicalOrder();

        return [
            'activation_order_len' => count($order),
            'has_activation_cycle' => $g->hasActivationCycle(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lifecycleProbe(): array
    {
        $c = new LifecycleBindingCoordinator();
        $sync = $c->synchronize([
            'commerce_state' => 'ACTIVE',
            'ownership_state' => 'OWNED',
            'provisioning_phase' => 'DNS_SETUP',
            'queue_state' => 'RUNNING',
            'provider_state' => 'HEALTHY',
        ], 'system', 'corr', 'trace');

        return ['sample_synchronized' => $sync['synchronized'], 'sample_warnings' => $sync['warnings']];
    }

    /**
     * @return array<string, mixed>|string
     */
    private static function mapperProbe(\PDO $pdo): array|string
    {
        try {
            $m = new OrderCommerceMapper(
                $pdo,
                new \RATEB\InfrastructureMarketplace\Catalog\CatalogRepository($pdo),
                new \RATEB\InfrastructureMarketplace\Commerce\PlanRepository($pdo),
                new \RATEB\InfrastructureMarketplace\Commerce\ProductRepository($pdo)
            );

            return [
                'sku_compat_sample' => $m->resolveSkuCompatibility('nonexistent-sku-test', new \RATEB\InfrastructureMarketplace\Domain\TenantContext(1, null)),
            ];
        } catch (\Throwable $e) {
            return 'ERROR:' . $e->getMessage();
        }
    }

    /**
     * @param array<string, mixed> $r
     */
    public static function toJson(array $r): string
    {
        $j = json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $j !== false ? $j : '{}';
    }

    /**
     * @param array<string, mixed> $r
     */
    public static function toHtml(array $r): string
    {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Commerce execution readiness</title>'
            . '<style>body{font-family:system-ui,sans-serif;margin:1.2rem;background:#0b1020;color:#e5e7eb}pre{background:#020617;padding:.75rem;border-radius:8px;overflow:auto}</style></head><body>'
            . '<h1>Commerce execution readiness</h1><pre>' . htmlspecialchars(self::toJson($r), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre></body></html>';
    }
}

if (PHP_SAPI === 'cli') {
    $norm = static fn (string $p): string => strtolower(str_replace('\\', '/', $p));
    $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($script !== '' && $norm($script) === $norm(__FILE__)) {
        require_once dirname(__DIR__) . '/bootstrap.php';
        $pdo = null;
        try {
            $pdo = \RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory::createPdo();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Note: ' . $e->getMessage() . "\n");
        }
        $r = CommerceExecutionReadinessReport::build($pdo);
        $dir = __DIR__;
        file_put_contents($dir . '/commerce-execution-readiness.json', CommerceExecutionReadinessReport::toJson($r));
        file_put_contents($dir . '/commerce-execution-readiness.html', CommerceExecutionReadinessReport::toHtml($r));
        fwrite(STDOUT, "Wrote commerce-execution-readiness.{json,html}\n");
    }
}
