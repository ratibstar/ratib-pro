<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Reports;

use Ratib\InfrastructureMarketplace\Infrastructure\SchemaHelpers;

/**
 * Phase 3 — execution-layer audit: orders, provisioning linkage, payment path, identity/ownership gaps.
 *
 * CLI: `php modules/infrastructure-marketplace/Reports/CommerceExecutionAuditReport.php`
 */
final class CommerceExecutionAuditReport
{
    private const MODULE_ROOT = __DIR__ . '/..';

    /**
     * @return array<string, mixed>
     */
    public static function build(?\PDO $pdo = null): array
    {
        $root = self::MODULE_ROOT;
        $f = static fn (string $rel): bool => is_file($root . '/' . $rel);

        $orderLifecycle = [
            'evidence' => [
                'ratib_infra_orders.status written as PENDING then QUEUED (OrderRepository::markQueued).',
                'LifecycleTracker::syncFromProvisioningJob copies job state into order.status (queue-shaped literals on order row).',
            ],
            'observed_status_literals' => ['PENDING', 'QUEUED', 'RUNNING', 'FAILED', '… (mirrors job queue when synced)'],
            'gap' => 'No first-class PAYMENT_PENDING / PAYMENT_CONFIRMED columns in code paths reviewed; payment confirmation is billing-bridge metadata only (InfrastructureBillingSynchronizer).',
        ];

        $activationFlow = [
            'legacy_path' => 'InfrastructureOrderService::place() creates order + submits ProvisioningOrchestrator immediately (no payment gate in module).',
            'phase3_path' => 'CommerceActivationEngine::activateAfterPaymentConfirmed() adds intent audit, tenant_resources overlay, optional enqueue when job absent, idempotent via commerce_activation_completed audit.',
            'public_ids' => 'Orders use UUID-style public_id; services use public_id linked by order_public_id (OrphanResourceReconciler).',
        ];

        $missingBindings = [
            'ratib_infra_orders has sku but no native plan_id / product_id FK (mapper resolves via plan_code or catalog).',
            'ProvisioningIntent is not persisted as its own DB row (audit + optional tenant_resources metadata only).',
            'No ratib_infra_order_line_items table in this module (recommended additive Phase 4).',
        ];

        $resourceIdentityGaps = [
            'CommerceActivationEngine issues new infra_resource public_id distinct from order_public_id and job id.',
            'Provider remote IDs remain adapter-owned; ResourceIdentityManager only formats canonical public ids + graph node keys.',
        ];

        $provisioningDrift = [
            'LifecycleTracker maps queue_state into order.status — risk of treating order row as commerce lifecycle.',
            'ratib_infra_services.lifecycle_state may use queue-like vocabulary (see foundation readiness).',
        ];

        $ownershipInconsistencies = [
            'ratib_tenant_resources optional overlay vs ratib_infra_services.order_public_id — dual tracking until reconciled.',
        ];

        $duplicateExecutionHazards = [
            'place() always enqueues; CommerceActivationEngine skips enqueue when provisioning_job_public_id already set.',
            'Replay protection: ExecutionSafetyLayer + commerce_activation_completed audit marker.',
        ];

        $providerCouplingRisks = [
            'ProviderExecutionBinder reads ProviderRegistry + ratib_infra_provider_activations only (no adapter calls).',
            'Misconfigured RATIB_INFRA_PROVIDER_BINDINGS yields empty execution target with warnings.',
        ];

        $reconciliationLogic = [
            'ProvisioningOrchestrator::reconcile() is foundation noop — drift repair is external/partial (OrphanResourceReconciler snapshot).',
        ];

        $files = [
            'Commerce/CommerceActivationEngine.php' => $f('Commerce/CommerceActivationEngine.php'),
            'Commerce/OrderCommerceMapper.php' => $f('Commerce/OrderCommerceMapper.php'),
            'Provisioning/ProvisioningIntent.php' => $f('Provisioning/ProvisioningIntent.php'),
            'Provisioning/ProvisioningIntentFactory.php' => $f('Provisioning/ProvisioningIntentFactory.php'),
            'Execution/ExecutionSafetyLayer.php' => $f('Execution/ExecutionSafetyLayer.php'),
            'Providers/ProviderExecutionBinder.php' => $f('Providers/ProviderExecutionBinder.php'),
            'Lifecycle/LifecycleBindingCoordinator.php' => $f('Lifecycle/LifecycleBindingCoordinator.php'),
            'Resources/ResourceIdentityManager.php' => $f('Resources/ResourceIdentityManager.php'),
            'Ordering/InfrastructureOrderService.php' => $f('Ordering/InfrastructureOrderService.php'),
            'Ordering/LifecycleTracker.php' => $f('Ordering/LifecycleTracker.php'),
        ];

        $dbProbe = self::dbProbe($pdo);

        return [
            'schema_version' => 'commerce-execution-audit-1.0',
            'generated_at' => gmdate('c'),
            '1_current_order_lifecycle' => $orderLifecycle,
            '2_current_activation_flow' => $activationFlow,
            '3_missing_execution_bindings' => $missingBindings,
            '4_resource_identity_gaps' => $resourceIdentityGaps,
            '5_provisioning_drift_risks' => $provisioningDrift,
            '6_ownership_inconsistencies' => $ownershipInconsistencies,
            '7_lifecycle_synchronization_gaps' => [
                'Order status vs plan commerce_state vs tenant ownership_state vs job queue_state require LifecycleBindingCoordinator snapshots (no shared enum).',
            ],
            '8_duplicate_execution_hazards' => $duplicateExecutionHazards,
            '9_provider_coupling_risks' => $providerCouplingRisks,
            '10_reconciliation_logic' => $reconciliationLogic,
            '11_phase3_files_present' => $files,
            '12_database_probe' => $dbProbe,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function dbProbe(?\PDO $pdo): array
    {
        if ($pdo === null) {
            return ['status' => 'SKIPPED_NO_PDO'];
        }
        $out = ['status' => 'ok'];
        foreach (['ratib_infra_orders', 'ratib_infra_services', 'ratib_infra_provisioning_jobs', 'ratib_infra_plans', 'ratib_tenant_resources', 'ratib_infra_audit_entries'] as $t) {
            try {
                $out['tables'][$t] = SchemaHelpers::tableExists($pdo, $t) ? 'PRESENT' : 'MISSING';
            } catch (\Throwable $e) {
                $out['tables'][$t] = 'ERROR:' . $e->getMessage();
            }
        }
        try {
            $stmt = $pdo->query('SELECT status, COUNT(*) c FROM ratib_infra_orders GROUP BY status');
            if ($stmt instanceof \PDOStatement) {
                $out['order_status_histogram'] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Throwable $e) {
            $out['order_status_histogram'] = 'ERROR:' . $e->getMessage();
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function toJson(array $report): string
    {
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json !== false ? $json : '{"error":"json_encode_failed"}';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function toHtml(array $report): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Commerce execution audit</title>'
            . '<style>body{font-family:system-ui,sans-serif;margin:1.2rem;background:#0b1020;color:#e5e7eb}pre{background:#020617;padding:.75rem;border-radius:8px;overflow:auto}</style></head><body>'
            . '<h1>Commerce execution audit</h1><pre>' . $esc(self::toJson($report)) . '</pre></body></html>';
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
        $r = CommerceExecutionAuditReport::build($pdo);
        $dir = __DIR__;
        file_put_contents($dir . '/commerce-execution-audit.json', CommerceExecutionAuditReport::toJson($r));
        file_put_contents($dir . '/commerce-execution-audit.html', CommerceExecutionAuditReport::toHtml($r));
        fwrite(STDOUT, "Wrote commerce-execution-audit.{json,html}\n");
    }
}
