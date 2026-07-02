<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use RuntimeException;
use Throwable;

final class AgencyErpMigrationService
{
    public const RESET_DATA_CONFIRM = 'RESET-DATA';
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
        $this->ensureAgencyLookup();
        if (function_exists('rateb_lookup_agency_by_erp_company_id')) {
            $linked = rateb_lookup_agency_by_erp_company_id($companyId);
            if (is_array($linked)) {
                return (int) ($linked['id'] ?? 0);
            }
        }
        foreach ($this->listAgencies(false) as $agency) {
            if ((int) ($agency['erp_company_id'] ?? 0) === $companyId) {
                return (int) ($agency['id'] ?? 0);
            }
        }

        return 0;
    }

    public function linkAgencyToCompany(int $agencyId, int $companyId): void
    {
        if ($agencyId < 1) {
            throw new RuntimeException(__('agency_erp_push_link_invalid_agency'));
        }
        if ($companyId > 0) {
            $company = (new \Rateb\App\Models\Company())->find($companyId);
            if ($company === null) {
                throw new RuntimeException(__('agency_erp_push_link_company_missing'));
            }
            $existing = $this->suggestedAgencyIdForCompany($companyId);
            if ($existing > 0 && $existing !== $agencyId) {
                throw new RuntimeException(__('agency_erp_push_link_company_taken'));
            }
        }
        $this->ensureAgencyLookup();
        if (!function_exists('rateb_save_agency_erp_company_link')
            || !rateb_save_agency_erp_company_link($agencyId, $companyId)) {
            throw new RuntimeException(__('agency_erp_push_link_failed'));
        }
    }

    /** @return array<int, string> */
    public function platformCompanyNames(): array
    {
        $map = [];
        foreach ((new \Rateb\App\Models\Company())->all(500, 0) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = trim((string) ($row['name'] ?? ''));
            }
        }

        return $map;
    }

    /** @return \PDO */
    public function agencyPdo(array $agency): \PDO
    {
        return $this->pdoFromConfig($this->agencyDatabaseConfig($agency));
    }

    /**
     * @param array{host:string,port:int,user:string,pass:string,db:string} $cfg
     */
    public function pdoFromConfig(array $cfg): \PDO
    {
        if ($cfg['db'] === '') {
            throw new RuntimeException('No ERP database configured');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['port'],
            $cfg['db']
        );

        return new \PDO($dsn, $cfg['user'], $cfg['pass'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Platform ERP credentials (rateb.sa context) — not per-agency DB user.
     *
     * @return array{host:string,port:int,user:string,pass:string,db:string}
     */
    public function platformErpDatabaseConfig(): array
    {
        $dbName = function_exists('rateb_platform_erp_database_name')
            ? trim((string) rateb_platform_erp_database_name())
            : 'admin_rateb-erp';
        if ($dbName === '') {
            $dbName = 'admin_rateb-erp';
        }
        $host = defined('RATEB_DB_HOST') ? (string) RATEB_DB_HOST : 'localhost';
        $port = defined('RATEB_DB_PORT') ? (int) RATEB_DB_PORT : 3306;
        if (function_exists('rateb_erp_db_credentials')) {
            [$user, $pass] = rateb_erp_db_credentials();
        } else {
            $user = defined('RATEB_DB_USER') ? (string) RATEB_DB_USER : 'root';
            $pass = defined('RATEB_DB_PASS') ? (string) RATEB_DB_PASS : '';
        }

        return [
            'host' => $host !== '' ? $host : 'localhost',
            'port' => $port > 0 ? $port : 3306,
            'user' => $user,
            'pass' => $pass,
            'db' => $dbName,
        ];
    }

    /**
     * @param array<string, mixed> $agency
     * @return list<int>
     */
    private function resolvePlatformCompanyIds(array $agency, \PDO $platformPdo): array
    {
        $ids = [];
        $linked = (int) ($agency['erp_company_id'] ?? 0);
        if ($linked > 0) {
            $ids[] = $linked;
        }

        $agencyName = trim((string) ($agency['name'] ?? ''));
        if ($agencyName !== '') {
            $stmt = $platformPdo->prepare(
                'SELECT id FROM rateb_companies WHERE LOWER(name) = LOWER(:n) OR LOWER(slug) = LOWER(:s) LIMIT 5'
            );
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $agencyName) ?? $agencyName);
            $stmt->execute(['n' => $agencyName, 's' => trim($slug, '-')]);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        if ($linked > 0) {
            try {
                $row = (new \Rateb\App\Models\Company())->find($linked);
                $platformName = trim((string) ($row['name'] ?? ''));
                if ($platformName !== '') {
                    $stmt = $platformPdo->prepare(
                        'SELECT id FROM rateb_companies WHERE LOWER(name) = LOWER(:n) LIMIT 5'
                    );
                    $stmt->execute(['n' => $platformName]);
                    while ($found = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $id = (int) ($found['id'] ?? 0);
                        if ($id > 0) {
                            $ids[] = $id;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore — fall back to linked id only
            }
        }

        $valid = [];
        foreach (array_unique($ids) as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $chk = $platformPdo->prepare('SELECT id FROM rateb_companies WHERE id = :id LIMIT 1');
            $chk->execute(['id' => $id]);
            if ($chk->fetch()) {
                $valid[] = $id;
            }
        }

        return $valid;
    }

    private function countPurchaseRequests(\PDO $pdo, ?int $companyId = null): int
    {
        try {
            if ($companyId !== null && $companyId > 0) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM rateb_purchase_requests WHERE company_id = :cid');
                $stmt->execute(['cid' => $companyId]);

                return (int) $stmt->fetchColumn();
            }

            return (int) $pdo->query('SELECT COUNT(*) FROM rateb_purchase_requests')->fetchColumn();
        } catch (\Throwable $e) {
            return -1;
        }
    }

    /**
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    public function restoreSuperAdminForAgency(array $agency, bool $resetPassword = true): array
    {
        $runnerFile = (defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2)) . '/bin/SuperAdminRestoreRunner.php';
        if (!is_file($runnerFile)) {
            throw new RuntimeException('SuperAdminRestoreRunner missing');
        }
        require_once $runnerFile;
        $pdo = $this->agencyPdo($agency);
        $runner = new \SuperAdminRestoreRunner($pdo);
        $report = $runner->restore($resetPassword);
        $report['agency_id'] = (int) ($agency['id'] ?? 0);
        $report['agency_name'] = trim((string) ($agency['name'] ?? ''));
        $report['erp_db_name'] = $this->agencyDatabaseConfig($agency)['db'];

        return $report;
    }

    /**
     * @param array{agency_ids?:list<int>,scope?:string} $options
     * @return array<string, mixed>
     */
    public function restoreSuperAdmins(array $options): array
    {
        $this->ensureAgencyLookup();
        $agencyIds = $options['agency_ids'] ?? [];
        if (!is_array($agencyIds)) {
            $agencyIds = [];
        }
        $agencyIds = array_values(array_unique(array_filter(array_map('intval', $agencyIds), static fn (int $id): bool => $id > 0)));
        $scope = strtolower(trim((string) ($options['scope'] ?? '')));
        if ($scope === 'all_ready' || $scope === 'all_subscribed') {
            $rows = $this->listAgencies($scope === 'all_subscribed');
            $agencyIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
            $agencyIds = array_values(array_filter($agencyIds, static fn (int $id): bool => $id > 0));
        }
        if ($agencyIds === []) {
            throw new RuntimeException(__('agency_erp_push_select_target'));
        }

        $results = [];
        $failed = 0;
        foreach ($agencyIds as $agencyId) {
            $agency = function_exists('rateb_lookup_agency_by_id') ? rateb_lookup_agency_by_id($agencyId) : null;
            if ($agency === null) {
                $results[] = ['agency_id' => $agencyId, 'ok' => false, 'error' => __('agency_erp_push_not_found')];
                $failed++;
                continue;
            }
            try {
                $report = $this->restoreSuperAdminForAgency($agency, true);
                $results[] = ['agency_id' => $agencyId, 'ok' => true, 'report' => $report];
            } catch (Throwable $e) {
                $results[] = [
                    'agency_id' => $agencyId,
                    'agency_name' => (string) ($agency['name'] ?? ''),
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
                $failed++;
            }
        }

        return [
            'success' => $failed === 0,
            'total' => count($results),
            'failed_count' => $failed,
            'results' => $results,
        ];
    }

    /**
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    public function resetAgencyData(array $agency): array
    {
        $agencyId = (int) ($agency['id'] ?? 0);
        $status = strtolower(trim((string) ($agency['erp_status'] ?? '')));
        if ($status !== 'ready') {
            throw new RuntimeException(__('agency_erp_reset_not_ready'));
        }

        $cfg = $this->agencyDatabaseConfig($agency);
        if ($cfg['db'] === '') {
            throw new RuntimeException('No ERP database configured for agency #' . $agencyId);
        }

        $siteHost = '';
        if (function_exists('rateb_agency_host_from_site_url')) {
            $siteHost = rateb_agency_host_from_site_url(trim((string) ($agency['site_url'] ?? '')));
        }
        if ($siteHost !== '' && function_exists('rateb_agency_erp_binding_for_host')) {
            $this->ensureAgencyLookup();
            $siteBinding = rateb_agency_erp_binding_for_host($siteHost);
            if (is_array($siteBinding) && trim((string) ($siteBinding['db'] ?? '')) !== '') {
                $cfg = [
                    'host' => (string) $siteBinding['host'],
                    'port' => (int) $siteBinding['port'],
                    'user' => (string) $siteBinding['user'],
                    'pass' => (string) $siteBinding['pass'],
                    'db' => (string) $siteBinding['db'],
                ];
            }
        }

        $platformDb = function_exists('rateb_platform_erp_database_name')
            ? trim((string) rateb_platform_erp_database_name())
            : '';
        if ($platformDb === '' && function_exists('rateb_erp_database_name')) {
            $platformDb = trim((string) rateb_erp_database_name());
        }
        if ($platformDb !== '' && strcasecmp($cfg['db'], $platformDb) === 0) {
            throw new RuntimeException(__('agency_erp_reset_platform_blocked'));
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        $runnerFile = (defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2)) . '/bin/ProductionResetRunner.php';
        if (!is_file($runnerFile)) {
            throw new RuntimeException('ProductionResetRunner missing');
        }
        require_once $runnerFile;

        $pdo = $this->pdoFromConfig($cfg);
        $runner = new \ProductionResetRunner($pdo, $cfg['db']);
        $runner->run(false, true, false, true);
        $report = $runner->report();
        $report['purchase_requests_before'] = $this->countPurchaseRequests($pdo);

        $shell = $this->rebuildAgencyShellPreserveLogins($agency, $cfg);
        if ($shell['company_id'] > 0 && function_exists('rateb_save_agency_erp_company_link')) {
            rateb_save_agency_erp_company_link($agencyId, (int) $shell['company_id']);
        }

        $platformCfg = $this->platformErpDatabaseConfig();
        $platformDb = $platformCfg['db'];
        $platformWipes = [];
        $platformErrors = [];
        if ($platformDb !== '' && strcasecmp($cfg['db'], $platformDb) !== 0) {
            try {
                $platformPdo = $this->pdoFromConfig($platformCfg);
                $companyIds = $this->resolvePlatformCompanyIds($agency, $platformPdo);
                $report['platform_company_ids'] = $companyIds;
                $report['platform_pr_before'] = $this->countPurchaseRequests($platformPdo);
                if ($companyIds === []) {
                    if ($report['platform_pr_before'] > 0) {
                        $platformErrors[] = 'Platform DB still has purchase data but no company id is linked (set erp_company_id on the agency).';
                    }
                } else {
                    foreach ($companyIds as $companyId) {
                        $platformWipes[] = $this->wipePlatformCompanyTenant($platformCfg, $companyId);
                    }
                    $report['platform_pr_after'] = $this->countPurchaseRequests($platformPdo);
                    if ($report['platform_pr_after'] > 0) {
                        $platformErrors[] = 'Platform still has ' . $report['platform_pr_after'] . ' purchase requests after company wipe.';
                    }
                }
            } catch (Throwable $e) {
                $platformErrors[] = $e->getMessage();
            }
        }

        if ($platformErrors !== []) {
            $report['platform_errors'] = $platformErrors;
            throw new RuntimeException(implode(' ', $platformErrors));
        }

        $report['agency_id'] = $agencyId;
        $report['agency_name'] = trim((string) ($agency['name'] ?? ''));
        $report['erp_db_name'] = $cfg['db'];
        $report['site_host'] = $siteHost;
        $report['shell'] = $shell;
        $report['platform_db'] = $platformDb;
        $report['platform_wipes'] = $platformWipes;

        return $report;
    }

    /**
     * @param array{agency_ids?:list<int>,scope?:string,confirm?:string} $options
     * @return array<string, mixed>
     */
    public function resetAgencyDataBulk(array $options): array
    {
        $confirm = strtoupper(trim((string) ($options['confirm'] ?? '')));
        if ($confirm !== self::RESET_DATA_CONFIRM) {
            throw new RuntimeException(__('agency_erp_reset_confirm_required'));
        }

        $this->ensureAgencyLookup();
        $agencyIds = $options['agency_ids'] ?? [];
        if (!is_array($agencyIds)) {
            $agencyIds = [];
        }
        $agencyIds = array_values(array_unique(array_filter(array_map('intval', $agencyIds), static fn (int $id): bool => $id > 0)));
        $scope = strtolower(trim((string) ($options['scope'] ?? '')));
        if ($scope === 'all_ready' || $scope === 'all_subscribed') {
            $rows = $this->listAgencies($scope === 'all_subscribed');
            $agencyIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
            $agencyIds = array_values(array_filter($agencyIds, static fn (int $id): bool => $id > 0));
        }
        if ($agencyIds === []) {
            throw new RuntimeException(__('agency_erp_push_select_target'));
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(1800);
        }

        $results = [];
        $failed = 0;
        $success = 0;
        foreach ($agencyIds as $agencyId) {
            $agency = function_exists('rateb_lookup_agency_by_id') ? rateb_lookup_agency_by_id($agencyId) : null;
            if ($agency === null) {
                $results[] = ['agency_id' => $agencyId, 'ok' => false, 'error' => __('agency_erp_push_not_found')];
                $failed++;
                continue;
            }
            try {
                $report = $this->resetAgencyData($agency);
                $results[] = [
                    'agency_id' => $agencyId,
                    'agency_name' => (string) ($agency['name'] ?? ''),
                    'erp_db_name' => (string) ($report['erp_db_name'] ?? ''),
                    'ok' => true,
                    'report' => $report,
                ];
                $success++;
            } catch (Throwable $e) {
                $results[] = [
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

    /**
     * @param array<string, mixed> $agency
     * @param array{host:string,port:int,user:string,pass:string,db:string} $cfg
     * @return array<string, mixed>
     */
    private function rebuildAgencyShellPreserveLogins(array $agency, array $cfg): array
    {
        Database::useConnectionOverride([
            'db' => $cfg['db'],
            'host' => $cfg['host'],
            'port' => $cfg['port'],
            'user' => $cfg['user'],
            'pass' => $cfg['pass'],
        ]);

        try {
            $companyName = trim((string) ($agency['name'] ?? 'Company'));
            if ($companyName === '') {
                $companyName = 'Company';
            }
            $planSlug = trim((string) ($agency['erp_plan_slug'] ?? 'professional'));
            if ($planSlug === '') {
                $planSlug = 'professional';
            }

            return (new DedicatedCompanySeedService())->rebuildShellPreserveLogins(
                $companyName,
                $planSlug
            );
        } finally {
            Database::clearConnectionOverride();
        }
    }

    /**
     * @param array{host:string,port:int,user:string,pass:string,db:string} $platformCfg
     * @return array<string, mixed>
     */
    private function wipePlatformCompanyTenant(array $platformCfg, int $companyId): array
    {
        $pdo = $this->pdoFromConfig($platformCfg);
        $wipe = (new CompanyTenantWipeService())->wipeCompany($pdo, $companyId, false);

        return array_merge(['ok' => true, 'database' => $platformCfg['db']], $wipe);
    }
}
