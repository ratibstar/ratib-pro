<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use RuntimeException;
use Throwable;

final class AgencyErpMigrationService
{
    private function ensureAgencyLookup(): void
    {
        if (function_exists('rateb_agency_lookup_connection')) {
            return;
        }
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $path = dirname($root) . '/config/env/agency_lookup.php';
        if (!is_file($path)) {
            throw new RuntimeException('Agency lookup configuration not found');
        }
        require_once $path;
    }

    /** @return list<array<string, mixed>> */
    public function listAgencies(bool $subscribedOnly = false): array
    {
        $this->ensureAgencyLookup();
        if (!function_exists('rateb_list_agencies_with_erp')) {
            return [];
        }

        return rateb_list_agencies_with_erp($subscribedOnly);
    }

    /**
     * @param array<string, mixed> $agency
     * @return array{host:string,port:int,user:string,pass:string,db:string}
     */
    public function agencyDatabaseConfig(array $agency): array
    {
        $dbName = trim((string) ($agency['erp_db_name'] ?? ''));
        $dbHost = trim((string) ($agency['erp_db_host'] ?? ''));
        if ($dbHost === '') {
            $dbHost = trim((string) ($agency['db_host'] ?? 'localhost'));
        }
        $dbPort = (int) ($agency['db_port'] ?? 3306);
        $dbUser = trim((string) ($agency['erp_db_user'] ?? ''));
        if ($dbUser === '') {
            $dbUser = trim((string) ($agency['db_user'] ?? ''));
        }
        $dbPass = (string) ($agency['erp_db_pass'] ?? '');
        if ($dbPass === '') {
            $dbPass = (string) ($agency['db_pass'] ?? '');
        }
        if ($dbName === '') {
            $dbName = trim((string) ($agency['db_name'] ?? ''));
        }

        return [
            'host' => $dbHost !== '' ? $dbHost : 'localhost',
            'port' => $dbPort > 0 ? $dbPort : 3306,
            'user' => $dbUser,
            'pass' => $dbPass,
            'db' => $dbName,
        ];
    }

    /** @return array<int, string> */
    public function runPlatformMigrations(): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        return (new MigrationService())->runAll();
    }

    /**
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    public function runForAgency(array $agency): array
    {
        $agencyId = (int) ($agency['id'] ?? 0);
        $cfg = $this->agencyDatabaseConfig($agency);
        if ($cfg['db'] === '') {
            throw new RuntimeException('No ERP database configured for agency #' . $agencyId);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $log = (new MigrationService())->runAllForDatabase($cfg);

        return [
            'agency_id' => $agencyId,
            'agency_name' => trim((string) ($agency['name'] ?? '')),
            'erp_db_name' => $cfg['db'],
            'log' => $log,
        ];
    }

    /**
     * @param array{agency_ids?:list<int>,scope?:string,include_platform?:bool} $options
     * @return array<string, mixed>
     */
    public function push(array $options): array
    {
        $this->ensureAgencyLookup();
        $scope = strtolower(trim((string) ($options['scope'] ?? '')));
        $includePlatform = !empty($options['include_platform']);
        $agencyIds = $options['agency_ids'] ?? [];
        if (!is_array($agencyIds)) {
            $agencyIds = [];
        }
        $agencyIds = array_values(array_unique(array_filter(array_map('intval', $agencyIds), static fn (int $id): bool => $id > 0)));

        if ($scope === 'all_ready' || $scope === 'all_subscribed') {
            $rows = $this->listAgencies($scope === 'all_subscribed');
            $agencyIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
            $agencyIds = array_values(array_filter($agencyIds, static fn (int $id): bool => $id > 0));
        }

        if ($agencyIds === [] && !$includePlatform) {
            throw new RuntimeException(__('agency_erp_push_select_target'));
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(900);
        }

        $results = [];
        $failed = 0;
        $success = 0;

        if ($includePlatform) {
            try {
                $log = $this->runPlatformMigrations();
                $platformDb = function_exists('rateb_erp_database_name') ? rateb_erp_database_name() : 'platform';
                $results[] = [
                    'target' => 'platform',
                    'label' => __('agency_erp_push_platform') . ' (' . $platformDb . ')',
                    'ok' => true,
                    'log' => $log,
                ];
                $success++;
            } catch (Throwable $e) {
                $results[] = [
                    'target' => 'platform',
                    'label' => __('agency_erp_push_platform'),
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
                $failed++;
            }
        }

        foreach ($agencyIds as $agencyId) {
            $agency = function_exists('rateb_lookup_agency_by_id') ? rateb_lookup_agency_by_id($agencyId) : null;
            if ($agency === null) {
                $results[] = [
                    'target' => 'agency',
                    'agency_id' => $agencyId,
                    'ok' => false,
                    'error' => __('agency_erp_push_not_found'),
                ];
                $failed++;
                continue;
            }
            try {
                $migration = $this->runForAgency($agency);
                $results[] = array_merge(['target' => 'agency', 'ok' => true], $migration);
                $success++;
            } catch (Throwable $e) {
                $results[] = [
                    'target' => 'agency',
                    'agency_id' => $agencyId,
                    'agency_name' => (string) ($agency['name'] ?? ''),
                    'erp_db_name' => (string) ($agency['erp_db_name'] ?? ''),
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
                $failed++;
            }
        }

        return [
            'success' => $failed === 0,
            'total' => count($results),
            'success_count' => $success,
            'failed_count' => $failed,
            'results' => $results,
        ];
    }

    public function suggestedAgencyIdForCompany(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        foreach ($this->listAgencies(false) as $agency) {
            if ((int) ($agency['tenant_id'] ?? 0) === $companyId) {
                return (int) ($agency['id'] ?? 0);
            }
        }

        return 0;
    }
}
