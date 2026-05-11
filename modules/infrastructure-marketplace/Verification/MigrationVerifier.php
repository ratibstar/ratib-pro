<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Verification;

final class MigrationVerifier
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

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
            $stmt = $this->pdo->prepare('SHOW TABLES LIKE :t');
            $stmt->execute(['t' => $table]);
            $row = $stmt->fetch(\PDO::FETCH_NUM);
            if (!is_array($row)) {
                $missing[] = $table;
            }
        }

        return [
            'required_tables' => $requiredTables,
            'missing_tables' => $missing,
            'status' => $missing === [] ? 'PASS' : 'FAIL',
        ];
    }
}

