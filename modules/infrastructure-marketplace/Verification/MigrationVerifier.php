<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Verification;

use RATEB\InfrastructureMarketplace\Infrastructure\SchemaHelpers;

final class MigrationVerifier
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }


    /**
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        $requiredTables = [
            'rateb_infra_catalog_items',
            'rateb_infra_provider_bindings',
            'rateb_infra_provisioning_jobs',
            'rateb_infra_job_logs',
            'rateb_infra_worker_heartbeats',
            'rateb_infra_audit_entries',
            'rateb_infra_secret_refs',
            'rateb_infra_provider_activations',
            'rateb_infra_provider_secrets',
            'rateb_infra_provider_events',
            'rateb_infra_orders',
            'rateb_infra_domain_search_cache',
            'rateb_infra_domain_search_rate',
            'rateb_infra_services',
        ];

        $missing = [];
        foreach ($requiredTables as $table) {
            if (!SchemaHelpers::tableExists($this->pdo, $table)) {
                $missing[] = $table;
            }
        }

        $optionalPhase2 = [
            'rateb_infra_products',
            'rateb_infra_plans',
            'rateb_infra_plan_features',
            'rateb_infra_pricing',
            'rateb_tenant_resources',
        ];
        $missingOptional = [];
        foreach ($optionalPhase2 as $table) {
            if (!SchemaHelpers::tableExists($this->pdo, $table)) {
                $missingOptional[] = $table;
            }
        }

        $providerSchemaColumns = [
            'provider_type',
            'provider_code',
            'provider_class',
            'tenant_id',
            'agency_id',
            'priority_weight',
            'is_enabled',
            'updated_by',
        ];
        $providerSchemaMissing = [];
        if (SchemaHelpers::tableExists($this->pdo, 'rateb_infra_provider_activations')) {
            foreach ($providerSchemaColumns as $column) {
                if (!SchemaHelpers::columnExists($this->pdo, 'rateb_infra_provider_activations', $column)) {
                    $providerSchemaMissing[] = $column;
                }
            }
        }

        $status = ($missing === [] && $providerSchemaMissing === []) ? 'PASS' : 'FAIL';

        return [
            'required_tables' => $requiredTables,
            'missing_tables' => $missing,
            'status' => $status,
            'optional_phase2_commerce_tables' => $optionalPhase2,
            'optional_phase2_missing' => $missingOptional,
            'provider_activation_schema_required_columns' => $providerSchemaColumns,
            'provider_activation_schema_missing' => $providerSchemaMissing,
        ];
    }
}

