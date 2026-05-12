<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Verification;

use Ratib\InfrastructureMarketplace\Infrastructure\SchemaHelpers;

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
            'ratib_infra_catalog_items',
            'ratib_infra_provider_bindings',
            'ratib_infra_provisioning_jobs',
            'ratib_infra_job_logs',
            'ratib_infra_worker_heartbeats',
            'ratib_infra_audit_entries',
            'ratib_infra_secret_refs',
            'ratib_infra_provider_activations',
            'ratib_infra_orders',
            'ratib_infra_domain_search_cache',
            'ratib_infra_domain_search_rate',
            'ratib_infra_services',
        ];

        $missing = [];
        foreach ($requiredTables as $table) {
            if (!SchemaHelpers::tableExists($this->pdo, $table)) {
                $missing[] = $table;
            }
        }

        $optionalPhase2 = [
            'ratib_infra_products',
            'ratib_infra_plans',
            'ratib_infra_plan_features',
            'ratib_infra_pricing',
            'ratib_tenant_resources',
        ];
        $missingOptional = [];
        foreach ($optionalPhase2 as $table) {
            if (!SchemaHelpers::tableExists($this->pdo, $table)) {
                $missingOptional[] = $table;
            }
        }

        return [
            'required_tables' => $requiredTables,
            'missing_tables' => $missing,
            'status' => $missing === [] ? 'PASS' : 'FAIL',
            'optional_phase2_commerce_tables' => $optionalPhase2,
            'optional_phase2_missing' => $missingOptional,
        ];
    }
}

