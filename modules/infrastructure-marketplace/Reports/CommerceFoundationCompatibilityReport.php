<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Reports;

/**
 * Phase 2 — commerce foundation compatibility strategy (read-only narrative + structured facts).
 *
 * CLI: `php modules/infrastructure-marketplace/Reports/CommerceFoundationCompatibilityReport.php`
 */
final class CommerceFoundationCompatibilityReport
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        return [
            'schema_version' => 'phase2-1.0',
            'generated_at' => gmdate('c'),
            'reused_systems' => [
                'CatalogRepository + ratib_infra_catalog_items (unchanged read path)',
                'ProvisioningState + ratib_infra_provisioning_jobs.status (queue semantics unchanged)',
                'ratib_infra_services for provisioned service rows',
                'InfrastructureAuditLogger + ratib_infra_audit_entries',
                'InfrastructureEventEmitter / correlation_id on jobs',
                'ProviderRegistry + ProviderActivationRegistry',
                'CapabilityDiscoveryService',
            ],
            'extended_systems' => [
                'New commerce tables sit beside catalog_items; CatalogCommerceBridge maps views only.',
                'ProductLifecycleManager updates ratib_infra_plans.commerce_state only (new column already in DDL).',
                'TenantResourceManager adds ratib_tenant_resources without altering ratib_infra_services DDL.',
                'StateNamespaceRegistry documents separation: queue vs commerce vs provisioning_phase vs ownership literals.',
            ],
            'avoided_conflicts' => [
                'No rename of ratib_infra_catalog_items, ratib_infra_provisioning_jobs, or job status strings.',
                'No merge of queue_state into commerce_state.',
                'Ownership literals (OWNED, UNCLAIMED, …) disjoint from ProvisioningState and commerce plan states.',
                'WAITING_EXTERNAL documented as queue-side ambiguous for provisioning phases; use WAITING_PROVIDER for phases.',
            ],
            'migration_safety_decisions' => [
                'CREATE TABLE IF NOT EXISTS only; no DROP, no ALTER of legacy tables in Phase2 scripts.',
                'FK from plans→products, features→plans, pricing→plans, tenant_resources→products/plans uses ON DELETE RESTRICT/CASCADE/SET NULL as appropriate; never cascade-delete catalog_items.',
                'Run order: 001_commerce_foundation_tables.sql then 002_tenant_resources_overlay.sql.',
            ],
            'enum_separation_strategy' => [
                'queue_state: ProvisioningState constants only on ratib_infra_provisioning_jobs.status.',
                'commerce_state: ratib_infra_plans.commerce_state + ProductLifecycleManager transitions.',
                'provisioning_phase: advisory strings for orchestration UI / metadata — not written into job status by this phase.',
                'ownership_state: ratib_tenant_resources.ownership_state uses OWNED|UNCLAIMED|DISABLED|PENDING_LINK only.',
            ],
            'compatibility_risks' => [
                'Dual catalog naming (ratib_infra_catalog_item vs items) remains documentation-only fix via SchemaAliasMap.',
                'Environments without Phase2 migrations: repositories will throw SQL errors — gate features by table presence in callers.',
                'ratib_infra_services.lifecycle_state VARCHAR may still echo queue-ish words; do not conflate with commerce_state.',
            ],
            'recommended_follow_ups' => [
                'Optional read-only API to expose compatibilityView() for admin tooling.',
                'Phase 3: bind ratib_infra_orders.sku to ratib_infra_plans.plan_code additively (nullable FK or mapping table).',
            ],
        ];
    }

    public static function toJson(array $r): string
    {
        $j = json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $j !== false ? $j : '{}';
    }

    public static function toHtml(array $r): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $blocks = '';
        foreach ($r as $k => $v) {
            if (!is_array($v)) {
                $blocks .= '<h2>' . $e((string) $k) . '</h2><p>' . $e((string) $v) . '</p>';
                continue;
            }
            $blocks .= '<h2>' . $e((string) $k) . '</h2><ul>';
            foreach ($v as $line) {
                $blocks .= '<li>' . $e((string) $line) . '</li>';
            }
            $blocks .= '</ul>';
        }

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Commerce foundation compatibility</title>'
            . '<style>body{font-family:system-ui,sans-serif;margin:1.2rem;background:#0b1020;color:#e5e7eb}h1{color:#f9fafb}h2{margin-top:1.25rem;font-size:1rem;color:#cbd5e1}</style></head><body>'
            . '<h1>Commerce foundation — compatibility</h1>' . $blocks . '</body></html>';
    }
}

if (PHP_SAPI === 'cli') {
    $norm = static fn (string $p): string => strtolower(str_replace('\\', '/', $p));
    $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($script !== '' && $norm($script) === $norm(__FILE__)) {
        $r = CommerceFoundationCompatibilityReport::build();
        $dir = __DIR__;
        file_put_contents($dir . '/commerce-foundation-compatibility.json', CommerceFoundationCompatibilityReport::toJson($r));
        file_put_contents($dir . '/commerce-foundation-compatibility.html', CommerceFoundationCompatibilityReport::toHtml($r));
        fwrite(STDOUT, "Wrote commerce-foundation-compatibility.{json,html}\n");
    }
}
