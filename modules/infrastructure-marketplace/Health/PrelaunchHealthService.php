<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Health;

use Ratib\InfrastructureMarketplace\Diagnostics\ProviderDiagnosticsService;
use Ratib\InfrastructureMarketplace\Verification\EnvironmentVerifier;
use Ratib\InfrastructureMarketplace\Verification\MigrationVerifier;
use Ratib\InfrastructureMarketplace\Verification\QueueWorkerVerifier;

final class PrelaunchHealthService
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $env = (new EnvironmentVerifier())->verify();
        $mig = (new MigrationVerifier($this->pdo))->verify();
        $queue = (new QueueWorkerVerifier($this->pdo))->verify();
        $providers = (new ProviderDiagnosticsService())->verify();
        $deploy = $this->deploymentVerification();
        $security = $this->securityVerification();
        $observability = $this->observabilityVerification();

        $matrix = $this->matrix([$env['checks'], $queue['checks'], $providers['checks'], $deploy['checks'], $security['checks'], $observability['checks']], $mig);
        return [
            'status' => $matrix['overall'],
            'score' => $matrix['score'],
            'matrix' => $matrix['counts'],
            'sections' => [
                'environment' => $env,
                'migrations' => $mig,
                'queue_worker' => $queue,
                'providers' => $providers,
                'deployment' => $deploy,
                'security' => $security,
                'observability' => $observability,
            ],
            'recommendations' => $this->recommendations($matrix, $mig, $queue),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deploymentVerification(): array
    {
        $requiredFiles = [
            dirname(__DIR__) . '/Workers/InfrastructureProvisioningWorker.php',
            dirname(__DIR__) . '/Views/admin/dashboard.php',
            dirname(__DIR__) . '/Assets/js/infrastructure-admin-dashboard.js',
            dirname(__DIR__) . '/Assets/css/infrastructure-admin-dashboard.css',
            dirname(__DIR__) . '/Migrations/005_provider_activation_marketplace.sql',
        ];
        $missing = [];
        foreach ($requiredFiles as $f) {
            if (!is_file($f)) {
                $missing[] = $f;
            }
        }
        $checks = [
            ['name' => 'required_assets_present', 'status' => $missing === [] ? 'PASS' : 'FAIL', 'missing_count' => count($missing)],
            ['name' => 'module_readable', 'status' => is_readable(dirname(__DIR__)) ? 'PASS' : 'FAIL'],
            ['name' => 'module_writable', 'status' => is_writable(dirname(__DIR__)) ? 'PASS' : 'WARN'],
        ];
        return ['checks' => $checks, 'missing_files' => $missing];
    }

    /**
     * @return array<string, mixed>
     */
    private function securityVerification(): array
    {
        $checks = [
            ['name' => 'secret_masking_helper_present', 'status' => class_exists('Ratib\\InfrastructureMarketplace\\Security\\Secrets\\SecretManager') ? 'PASS' : 'FAIL'],
            ['name' => 'audit_table_present', 'status' => $this->tableExists('ratib_infra_audit_entries') ? 'PASS' : 'FAIL'],
            ['name' => 'tenant_isolation_component_present', 'status' => class_exists('Ratib\\InfrastructureMarketplace\\Compliance\\TenantIsolationCompliance') ? 'PASS' : 'FAIL'],
            ['name' => 'admin_action_traceability_present', 'status' => class_exists('Ratib\\InfrastructureMarketplace\\Compliance\\AdminActionHistory') ? 'PASS' : 'FAIL'],
        ];
        return ['checks' => $checks];
    }

    /**
     * @return array<string, mixed>
     */
    private function observabilityVerification(): array
    {
        $checks = [
            ['name' => 'alerting_service_present', 'status' => class_exists('Ratib\\InfrastructureMarketplace\\Observability\\InfrastructureAlertingService') ? 'PASS' : 'FAIL'],
            ['name' => 'provider_outage_alert_path', 'status' => method_exists('Ratib\\InfrastructureMarketplace\\Observability\\InfrastructureAlertingService', 'providerOutage') ? 'PASS' : 'FAIL'],
            ['name' => 'worker_failure_alert_path', 'status' => method_exists('Ratib\\InfrastructureMarketplace\\Observability\\InfrastructureAlertingService', 'workerFailure') ? 'PASS' : 'FAIL'],
            ['name' => 'queue_saturation_alert_path', 'status' => method_exists('Ratib\\InfrastructureMarketplace\\Observability\\InfrastructureAlertingService', 'queueSaturation') ? 'PASS' : 'FAIL'],
            ['name' => 'ssl_expiration_alert_path', 'status' => method_exists('Ratib\\InfrastructureMarketplace\\Observability\\InfrastructureAlertingService', 'sslExpiration') ? 'PASS' : 'FAIL'],
            ['name' => 'reconciliation_anomaly_alert_path', 'status' => method_exists('Ratib\\InfrastructureMarketplace\\Observability\\InfrastructureAlertingService', 'reconciliationAnomaly') ? 'PASS' : 'FAIL'],
        ];
        return ['checks' => $checks];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE :t');
        $stmt->execute(['t' => $table]);
        return is_array($stmt->fetch(\PDO::FETCH_NUM));
    }

    /**
     * @param list<list<array<string, mixed>>> $sections
     * @return array<string, mixed>
     */
    private function matrix(array $sections, array $migration): array
    {
        $counts = ['PASS' => 0, 'WARN' => 0, 'FAIL' => 0];
        foreach ($sections as $section) {
            foreach ($section as $check) {
                $status = (string) ($check['status'] ?? 'WARN');
                if (!isset($counts[$status])) {
                    continue;
                }
                $counts[$status]++;
            }
        }
        $counts[$migration['status'] ?? 'WARN']++;
        $total = max(1, $counts['PASS'] + $counts['WARN'] + $counts['FAIL']);
        $score = (int) round(($counts['PASS'] / $total) * 100);
        $overall = $counts['FAIL'] > 0 ? 'FAIL' : ($counts['WARN'] > 0 ? 'WARN' : 'PASS');
        return ['counts' => $counts, 'score' => $score, 'overall' => $overall];
    }

    /**
     * @return list<string>
     */
    private function recommendations(array $matrix, array $migration, array $queue): array
    {
        $out = [];
        if (($migration['status'] ?? 'FAIL') !== 'PASS') {
            $out[] = 'Apply missing infrastructure migrations before launch.';
        }
        if ((int) ($queue['summary']['stale_workers'] ?? 0) > 0) {
            $out[] = 'Restart stale workers and verify heartbeat freshness.';
        }
        if ((int) ($queue['summary']['dead_letter'] ?? 0) > 0) {
            $out[] = 'Run dead-letter recovery drills and clear backlog.';
        }
        if (($matrix['overall'] ?? 'WARN') !== 'PASS') {
            $out[] = 'Keep rollout allowlist narrow until all WARN/FAIL items are resolved.';
        }
        if ($out === []) {
            $out[] = 'System is launch-ready for staged tenant rollout.';
        }
        return $out;
    }
}

