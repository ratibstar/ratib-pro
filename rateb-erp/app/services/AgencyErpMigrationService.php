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
     * All CP agencies (including before ERP DB provision) — platform companies mirror.
     *
     * @return list<array<string, mixed>>
     */
    public function listControlAgencies(bool $activeOnly = false): array
    {
        $this->ensureAgencyLookup();
        if (function_exists('rateb_list_control_agencies')) {
            return rateb_list_control_agencies($activeOnly);
        }

        return $this->listAgencies(false);
    }

    /**
     * Ensure platform rateb_companies row exists for this Control Panel agency and link erp_company_id.
     * Agency = company (same client).
     */
    public function ensurePlatformCompanyForAgency(array $agency): int
    {
        $agencyId = (int) ($agency['id'] ?? 0);
        if ($agencyId < 1) {
            throw new RuntimeException(__('agency_erp_push_link_invalid_agency'));
        }

        $linked = (int) ($agency['erp_company_id'] ?? 0);
        $companies = new \Rateb\App\Models\Company();
        if ($linked > 0) {
            $existing = $companies->find($linked);
            if ($existing !== null) {
                return $linked;
            }
        }

        $name = trim((string) ($agency['name'] ?? ''));
        if ($name === '') {
            $name = 'Agency #' . $agencyId;
        }
        $slugBase = trim((string) ($agency['slug'] ?? ''));
        if ($slugBase === '') {
            $slugBase = 'agency-' . $agencyId;
        }
        $slugBase = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $slugBase), '-'));
        if ($slugBase === '') {
            $slugBase = 'agency-' . $agencyId;
        }
        $slug = $slugBase;
        $n = 0;
        while ($companies->findBySlug($slug) !== null) {
            $n++;
            $slug = $slugBase . '-' . $n;
            if ($n > 50) {
                $slug = $slugBase . '-' . $agencyId . '-' . time();
                break;
            }
        }

        $site = trim((string) ($agency['site_url'] ?? ''));
        $emailLocal = 'agency' . $agencyId;
        $email = $emailLocal . '@rateb.sa';

        $isActive = (int) ($agency['is_active'] ?? 1) === 1;
        $isSuspended = (int) ($agency['is_suspended'] ?? 0) === 1;
        $status = $isSuspended ? 'suspended' : ($isActive ? 'active' : 'pending');

        $companyId = (int) $companies->create([
            'name' => $name,
            'slug' => $slug,
            'email' => $email,
            'phone' => '',
            'status' => $status,
            'modules' => json_encode(\Rateb\App\Services\PlanLimitService::defaultModules(), JSON_UNESCAPED_UNICODE),
            'user_limit' => 25,
            'storage_limit_mb' => 2048,
            'settings' => json_encode([
                'control_agency_id' => $agencyId,
                'site_url' => $site,
            ], JSON_UNESCAPED_UNICODE),
        ]);
        if ($companyId < 1) {
            throw new RuntimeException(__('company_admin_create_failed'));
        }

        (new AuthorizationService())->ensureCompanyRoles($companyId);
        (new BranchService())->ensureMainBranch($companyId);
        $this->linkAgencyToCompany($agencyId, $companyId);

        return $companyId;
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

        if ($success > 0 && function_exists('opcache_reset')) {
            @opcache_reset();
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

    /**
     * Read dedicated ERP admin login (agency DB — not platform rateb.sa users).
     *
     * @return array{username:string,email:string,user_id:int,company_id:int}|null
     */
    public function readDedicatedAdminLogin(array $agency): ?array
    {
        $cfg = $this->agencyDatabaseConfig($agency);
        if ($cfg['db'] === '') {
            return null;
        }
        Database::useConnectionOverride([
            'db' => $cfg['db'],
            'host' => $cfg['host'],
            'port' => $cfg['port'],
            'user' => $cfg['user'],
            'pass' => $cfg['pass'],
        ]);
        try {
            $row = $this->findDedicatedAdminUserRow();
            if ($row === null) {
                return null;
            }
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            $username = $name !== '' ? strtolower($name) : $email;
            if ($email === DedicatedCompanySeedService::DEFAULT_EMAIL || $username === 'admin') {
                $username = DedicatedCompanySeedService::DEFAULT_LOGIN;
            } elseif ($email !== '' && !str_ends_with($email, '@local') && !str_ends_with($email, '@rateb.sa')) {
                $username = $email;
            }

            return [
                'username' => $username,
                'email' => $email,
                'user_id' => (int) ($row['id'] ?? 0),
                'company_id' => (int) ($row['company_id'] ?? 0),
            ];
        } finally {
            Database::clearConnectionOverride();
        }
    }

    /**
     * Push username/password into the agency dedicated ERP database.
     * Blank password keeps the current hash.
     *
     * @return array{username:string,email:string,user_id:int,company_id:int}
     */
    public function syncDedicatedAdminLogin(array $agency, string $username, string $password): array
    {
        $cfg = $this->agencyDatabaseConfig($agency);
        if ($cfg['db'] === '') {
            throw new RuntimeException(__('company_agency_admin_no_db'));
        }
        $username = trim($username);
        if ($username === '') {
            throw new RuntimeException(__('company_admin_login_required'));
        }
        if ($password !== '' && strlen($password) < 6) {
            throw new RuntimeException(__('company_admin_login_required'));
        }

        Database::useConnectionOverride([
            'db' => $cfg['db'],
            'host' => $cfg['host'],
            'port' => $cfg['port'],
            'user' => $cfg['user'],
            'pass' => $cfg['pass'],
        ]);
        try {
            $companyRow = (new \Rateb\App\Models\Company())->queryOne(
                'SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1'
            );
            $companyId = (int) ($companyRow['id'] ?? 0);
            if ($companyId < 1) {
                throw new RuntimeException(__('company_agency_admin_no_company'));
            }

            if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $email = strtolower($username);
                $displayName = (string) strstr($email, '@', true);
                if ($displayName === '') {
                    $displayName = DedicatedCompanySeedService::DEFAULT_LOGIN;
                }
            } else {
                $safe = strtolower(trim((string) preg_replace('/[^a-z0-9._-]+/i', '', $username)));
                if ($safe === '') {
                    $safe = DedicatedCompanySeedService::DEFAULT_LOGIN;
                }
                $displayName = $safe;
                $email = $safe === DedicatedCompanySeedService::DEFAULT_LOGIN
                    ? DedicatedCompanySeedService::DEFAULT_EMAIL
                    : ($safe . '@local');
            }

            $users = new \Rateb\App\Models\User();
            $existing = $this->findDedicatedAdminUserRow();
            $payload = [
                'company_id' => $companyId,
                'name' => $displayName,
                'email' => $email,
                'status' => 'active',
                'is_super_admin' => 0,
            ];
            if ($password !== '') {
                $payload['password'] = password_hash($password, PASSWORD_DEFAULT);
            } elseif ($existing === null) {
                throw new RuntimeException(__('company_admin_login_required'));
            }

            if ($existing !== null) {
                $userId = (int) ($existing['id'] ?? 0);
                $users->update($userId, $payload);
            } else {
                $userId = (int) $users->create(array_merge($payload, [
                    'password' => password_hash(
                        $password !== '' ? $password : DedicatedCompanySeedService::DEFAULT_PASSWORD,
                        PASSWORD_DEFAULT
                    ),
                    'locale' => 'ar',
                ]));
            }
            if ($userId < 1) {
                throw new RuntimeException(__('company_admin_create_failed'));
            }

            (new AuthorizationService())->ensureCompanyRoles($companyId);
            $role = (new AuthorizationService())->findRoleBySlug('company-full-access', $companyId);
            if ($role) {
                (new AuthorizationService())->assignRole($userId, (int) $role['id']);
            }
            if (class_exists(BarcodeLoginService::class)) {
                (new BarcodeLoginService())->ensureUserBarcode($userId);
            }

            return [
                'username' => $displayName === DedicatedCompanySeedService::DEFAULT_LOGIN
                    ? DedicatedCompanySeedService::DEFAULT_LOGIN
                    : (filter_var($username, FILTER_VALIDATE_EMAIL) ? $email : $displayName),
                'email' => $email,
                'user_id' => $userId,
                'company_id' => $companyId,
            ];
        } finally {
            Database::clearConnectionOverride();
        }
    }

    /** @return array<string, mixed>|null */
    private function findDedicatedAdminUserRow(): ?array
    {
        $users = new \Rateb\App\Models\User();
        $row = $users->queryOne(
            "SELECT u.* FROM rateb_users u
             INNER JOIN rateb_user_roles ur ON ur.user_id = u.id
             INNER JOIN rateb_roles r ON r.id = ur.role_id AND r.slug = 'company-full-access'
             WHERE COALESCE(u.is_super_admin, 0) = 0
             ORDER BY u.id ASC
             LIMIT 1"
        );
        if ($row) {
            return $row;
        }
        $row = $users->queryOne(
            "SELECT * FROM rateb_users
             WHERE COALESCE(is_super_admin, 0) = 0
               AND (
                 email = :em
                 OR email LIKE 'admin+%'
                 OR LOWER(name) = 'admin'
               )
             ORDER BY id ASC
             LIMIT 1",
            ['em' => DedicatedCompanySeedService::DEFAULT_EMAIL]
        );
        if ($row) {
            return $row;
        }

        return $users->queryOne(
            'SELECT * FROM rateb_users
             WHERE COALESCE(is_super_admin, 0) = 0
             ORDER BY id ASC
             LIMIT 1'
        );
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
    private function resolvePlatformCompanyIds(
        array $agency,
        \PDO $platformPdo,
        ?\PDO $agencyPdo = null,
        ?int $forcePlatformCompanyId = null
    ): array {
        if ($forcePlatformCompanyId !== null && $forcePlatformCompanyId > 0) {
            $forced = $this->validatePlatformCompanyIds($platformPdo, [$forcePlatformCompanyId]);
            if ($forced !== []) {
                return $forced;
            }
        }

        $agencyId = (int) ($agency['id'] ?? 0);
        $ids = [];
        $linked = (int) ($agency['erp_company_id'] ?? 0);
        if ($linked > 0) {
            $ids[] = $linked;
        }

        $this->collectPlatformCompanyIdsByName($platformPdo, $ids, trim((string) ($agency['name'] ?? '')));

        $slug = trim((string) ($agency['slug'] ?? ''));
        if ($slug !== '') {
            $this->collectPlatformCompanyIdsBySlug($platformPdo, $ids, $slug);
        }

        $siteHost = '';
        if (function_exists('rateb_agency_host_from_site_url')) {
            $siteHost = rateb_agency_host_from_site_url(trim((string) ($agency['site_url'] ?? '')));
        }
        if ($siteHost !== '') {
            $this->collectPlatformCompanyIdsBySiteHost($platformPdo, $ids, $siteHost);
        }

        $this->collectPlatformCompanyIdsByAgencyEmail($platformPdo, $ids, $agency);

        if ($agencyPdo !== null) {
            foreach ($this->agencyCompanyHints($agencyPdo) as $hint) {
                $this->collectPlatformCompanyIdsByName($platformPdo, $ids, (string) ($hint['name'] ?? ''));
                $this->collectPlatformCompanyIdsBySlug($platformPdo, $ids, (string) ($hint['slug'] ?? ''));
                $email = trim((string) ($hint['email'] ?? ''));
                if ($email !== '' && str_contains($email, '@')) {
                    $this->collectPlatformCompanyIdsByEmail($platformPdo, $ids, $email);
                }
            }
        }

        if ($linked > 0) {
            try {
                $stmt = $platformPdo->prepare('SELECT name, slug FROM rateb_companies WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $linked]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $this->collectPlatformCompanyIdsByName($platformPdo, $ids, (string) ($row['name'] ?? ''));
                    $this->collectPlatformCompanyIdsBySlug($platformPdo, $ids, (string) ($row['slug'] ?? ''));
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $valid = $this->validatePlatformCompanyIds($platformPdo, $ids);
        if ($valid !== []) {
            return $valid;
        }

        return $this->discoverPlatformCompaniesWithData($platformPdo, $agency, $agencyId);
    }

    /** @param list<int> $ids */
    private function collectPlatformCompanyIdsByName(\PDO $platformPdo, array &$ids, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }
        $slug = strtolower(trim((string) (preg_replace('/[^a-z0-9]+/', '-', $name) ?? $name), '-'));
        try {
            $stmt = $platformPdo->prepare(
                'SELECT id FROM rateb_companies
                 WHERE LOWER(name) = LOWER(:n) OR LOWER(slug) = LOWER(:s) LIMIT 5'
            );
            $stmt->execute(['n' => $name, 's' => $slug]);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /** @param list<int> $ids */
    private function collectPlatformCompanyIdsBySlug(\PDO $platformPdo, array &$ids, string $slug): void
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return;
        }
        try {
            $stmt = $platformPdo->prepare(
                'SELECT id FROM rateb_companies WHERE LOWER(slug) = LOWER(:s) LIMIT 5'
            );
            $stmt->execute(['s' => $slug]);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /** @param list<int> $ids */
    private function collectPlatformCompanyIdsBySiteHost(\PDO $platformPdo, array &$ids, string $host): void
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return;
        }
        $parts = explode('.', $host);
        $sub = trim((string) ($parts[0] ?? ''));
        if ($sub !== '' && $sub !== 'www') {
            $this->collectPlatformCompanyIdsBySlug($platformPdo, $ids, $sub);
        }
        try {
            $stmt = $platformPdo->prepare(
                "SELECT id FROM rateb_companies
                 WHERE LOWER(slug) LIKE :like OR LOWER(name) LIKE :like
                 LIMIT 5"
            );
            $stmt->execute(['like' => '%' . $sub . '%']);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /** @param array<string, mixed> $agency
     * @param list<int> $ids */
    private function collectPlatformCompanyIdsByAgencyEmail(\PDO $platformPdo, array &$ids, array $agency): void
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '', strtolower((string) ($agency['slug'] ?? 'client')));
        if ($slug === '') {
            $slug = 'client' . (int) ($agency['id'] ?? 0);
        }
        $this->collectPlatformCompanyIdsByEmail($platformPdo, $ids, 'admin+' . $slug . '@rateb.sa');
    }

    /** @param list<int> $ids */
    private function collectPlatformCompanyIdsByEmail(\PDO $platformPdo, array &$ids, string $email): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || !str_contains($email, '@')) {
            return;
        }
        try {
            $stmt = $platformPdo->prepare('SELECT id FROM rateb_companies WHERE LOWER(email) = :e LIMIT 5');
            $stmt->execute(['e' => $email]);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /** @return list<array{name:string,slug:string,email:string}> */
    private function agencyCompanyHints(\PDO $agencyPdo): array
    {
        try {
            $rows = $agencyPdo->query('SELECT name, slug, email FROM rateb_companies ORDER BY id ASC LIMIT 20')->fetchAll(\PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @param list<int> $ids
     * @return list<int> */
    private function validatePlatformCompanyIds(\PDO $platformPdo, array $ids): array
    {
        $valid = [];
        foreach (array_unique($ids) as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            try {
                $chk = $platformPdo->prepare('SELECT id FROM rateb_companies WHERE id = :id LIMIT 1');
                $chk->execute(['id' => $id]);
                if ($chk->fetch()) {
                    $valid[] = $id;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $valid;
    }

    /**
     * Reserve a platform company only for another agency on the same site host.
     *
     * @param array<string, mixed>|null $forAgency
     * @return list<int>
     */
    private function platformCompanyIdsTakenByOtherAgencies(int $excludeAgencyId, ?array $forAgency = null): array
    {
        $resetHost = '';
        if (is_array($forAgency) && function_exists('rateb_agency_host_from_site_url')) {
            $resetHost = rateb_agency_host_from_site_url(trim((string) ($forAgency['site_url'] ?? '')));
        }

        $taken = [];
        foreach ($this->listAgencies(false) as $row) {
            $agencyId = (int) ($row['id'] ?? 0);
            if ($agencyId < 1 || $agencyId === $excludeAgencyId) {
                continue;
            }
            $linked = (int) ($row['erp_company_id'] ?? 0);
            if ($linked < 1) {
                continue;
            }
            if ($resetHost !== '' && function_exists('rateb_agency_host_from_site_url')) {
                $otherHost = rateb_agency_host_from_site_url(trim((string) ($row['site_url'] ?? '')));
                if ($otherHost !== '' && strcasecmp($otherHost, $resetHost) !== 0) {
                    continue;
                }
            }
            $taken[] = $linked;
        }

        return array_values(array_unique($taken));
    }

    /**
     * @param array<string, mixed> $agency
     * @return list<int>
     */
    private function discoverPlatformCompaniesWithData(\PDO $platformPdo, array $agency, int $agencyId): array
    {
        $taken = $this->platformCompanyIdsTakenByOtherAgencies($agencyId, $agency);
        $candidates = $this->collectPlatformBusinessCandidates($platformPdo, $agency, $taken);
        if ($candidates === []) {
            $candidates = $this->collectPlatformBusinessCandidates($platformPdo, $agency, []);
        }

        $picked = $this->pickBestPlatformCandidate(
            $candidates,
            (int) ($agency['erp_company_id'] ?? 0) < 1
        );
        if ($picked !== []) {
            return $picked;
        }

        if ((int) ($agency['erp_company_id'] ?? 0) < 1) {
            return $this->topPlatformCompanyWithBusinessData($platformPdo);
        }

        return [];
    }

    /**
     * @param list<int> $excludeIds
     * @return list<array{id:int,pr_count:int,score:int}>
     */
    private function collectPlatformBusinessCandidates(\PDO $platformPdo, array $agency, array $excludeIds): array
    {
        $scores = [];
        try {
            $stmt = $platformPdo->query(
                'SELECT company_id, COUNT(*) AS c
                 FROM rateb_purchase_requests
                 GROUP BY company_id
                 HAVING c > 0'
            );
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $companyId = (int) ($row['company_id'] ?? 0);
                if ($companyId < 1 || in_array($companyId, $excludeIds, true)) {
                    continue;
                }
                $prCount = (int) ($row['c'] ?? 0);
                $scores[$companyId] = ($scores[$companyId] ?? 0) + $prCount;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $stmt = $platformPdo->query(
                'SELECT company_id, COUNT(*) AS c
                 FROM rateb_inventory
                 GROUP BY company_id
                 HAVING c > 0'
            );
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $companyId = (int) ($row['company_id'] ?? 0);
                if ($companyId < 1 || in_array($companyId, $excludeIds, true)) {
                    continue;
                }
                $scores[$companyId] = ($scores[$companyId] ?? 0) + (int) ($row['c'] ?? 0);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        foreach (['rateb_suppliers', 'rateb_supplier_classifications'] as $table) {
            try {
                $stmt = $platformPdo->query(
                    "SELECT company_id, COUNT(*) AS c
                     FROM {$table}
                     GROUP BY company_id
                     HAVING c > 0"
                );
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $companyId = (int) ($row['company_id'] ?? 0);
                    if ($companyId < 1 || in_array($companyId, $excludeIds, true)) {
                        continue;
                    }
                    $scores[$companyId] = ($scores[$companyId] ?? 0) + (int) ($row['c'] ?? 0);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $candidates = [];
        foreach ($scores as $companyId => $weight) {
            $candidates[] = [
                'id' => (int) $companyId,
                'pr_count' => (int) $weight,
                'score' => (int) $weight + ($this->platformCompanyMatchScore($platformPdo, (int) $companyId, $agency) * 1000),
            ];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $candidates;
    }

    /**
     * @param list<array{id:int,pr_count:int,score:int}> $candidates
     * @return list<int>
     */
    private function pickBestPlatformCandidate(array $candidates, bool $allowAmbiguous = false): array
    {
        if ($candidates === []) {
            return [];
        }

        $top = $candidates[0];
        $second = $candidates[1] ?? null;
        if ($second === null || $allowAmbiguous) {
            return [(int) $top['id']];
        }
        if ($top['score'] > (int) $second['score']) {
            return [(int) $top['id']];
        }
        if ((int) $top['pr_count'] >= 2 * max(1, (int) ($second['pr_count'] ?? 0))) {
            return [(int) $top['id']];
        }
        if ($allowAmbiguous) {
            return [(int) $top['id']];
        }

        return [(int) $top['id']];
    }

    /** @return list<int> */
    private function topPlatformCompanyWithBusinessData(\PDO $platformPdo): array
    {
        try {
            $stmt = $platformPdo->query(
                'SELECT company_id, COUNT(*) AS c
                 FROM rateb_purchase_requests
                 GROUP BY company_id
                 ORDER BY c DESC
                 LIMIT 1'
            );
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
            $companyId = is_array($row) ? (int) ($row['company_id'] ?? 0) : 0;
            if ($companyId > 0) {
                return [$companyId];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $stmt = $platformPdo->query(
                'SELECT company_id, COUNT(*) AS c
                 FROM rateb_inventory
                 GROUP BY company_id
                 ORDER BY c DESC
                 LIMIT 1'
            );
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
            $companyId = is_array($row) ? (int) ($row['company_id'] ?? 0) : 0;
            if ($companyId > 0) {
                return [$companyId];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        foreach (['rateb_suppliers', 'rateb_supplier_classifications'] as $table) {
            try {
                $stmt = $platformPdo->query(
                    "SELECT company_id, COUNT(*) AS c
                     FROM {$table}
                     GROUP BY company_id
                     ORDER BY c DESC
                     LIMIT 1"
                );
                $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
                $companyId = is_array($row) ? (int) ($row['company_id'] ?? 0) : 0;
                if ($companyId > 0) {
                    return [$companyId];
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return [];
    }

    /** @param array<string, mixed> $agency */
    private function platformCompanyMatchScore(\PDO $platformPdo, int $companyId, array $agency): int
    {
        try {
            $stmt = $platformPdo->prepare('SELECT name, slug, email FROM rateb_companies WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $companyId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return 0;
            }
        } catch (\Throwable $e) {
            return 0;
        }

        $score = 0;
        $name = strtolower(trim((string) ($row['name'] ?? '')));
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));
        $agencyName = strtolower(trim((string) ($agency['name'] ?? '')));
        $agencySlug = strtolower(trim((string) ($agency['slug'] ?? '')));
        if ($agencyName !== '' && $name === $agencyName) {
            $score += 3;
        }
        if ($agencySlug !== '' && $slug === $agencySlug) {
            $score += 3;
        }

        $siteHost = '';
        if (function_exists('rateb_agency_host_from_site_url')) {
            $siteHost = rateb_agency_host_from_site_url(trim((string) ($agency['site_url'] ?? '')));
        }
        if ($siteHost !== '') {
            $sub = strtolower(trim((string) (explode('.', $siteHost)[0] ?? '')));
            if ($sub !== '' && ($slug === $sub || str_contains($slug, $sub) || str_contains($name, $sub))) {
                $score += 2;
            }
        }

        return $score;
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

    private function countPlatformTableRows(\PDO $pdo, string $table, ?int $companyId = null): int
    {
        try {
            if ($companyId !== null && $companyId > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE company_id = :cid");
                $stmt->execute(['cid' => $companyId]);

                return (int) $stmt->fetchColumn();
            }

            return (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
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
    public function resetAgencyData(array $agency, ?int $platformCompanyOverride = null): array
    {
        $agencyId = (int) ($agency['id'] ?? 0);
        $status = strtolower(trim((string) ($agency['erp_status'] ?? '')));

        $cfg = $this->agencyDatabaseConfig($agency);
        if ($cfg['db'] === '') {
            throw new RuntimeException(__('agency_erp_reset_no_db'));
        }

        $platformCompanyOverride = $this->resolveResetPlatformCompanyForAgency($agency, $platformCompanyOverride);

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
        $platformCfg = $this->platformErpDatabaseConfig();
        $platformDb = $platformCfg['db'];
        $platformCompanyIds = [];
        $platformPdo = null;
        if ($platformDb !== '' && strcasecmp($cfg['db'], $platformDb) !== 0) {
            $platformPdo = $this->pdoFromConfig($platformCfg);
            $platformCompanyIds = $this->resolvePlatformCompanyIds($agency, $platformPdo, $pdo, $platformCompanyOverride);
        }

        $runner = new \ProductionResetRunner($pdo, $cfg['db']);
        $runner->run(false, true, false, true);
        $report = $runner->report();
        $report['purchase_requests_before'] = $this->countPurchaseRequests($pdo);

        $shell = $this->rebuildAgencyShellPreserveLogins($agency, $cfg);
        $keepCompanyId = (int) ($shell['company_id'] ?? 0);
        if ($keepCompanyId > 0) {
            $shell['orphan_cleanup'] = $this->purgeExtraAgencyTenantData($pdo, $keepCompanyId);
        }

        $platformWipes = [];
        $platformErrors = [];
        if ($platformPdo instanceof \PDO && $platformDb !== '' && strcasecmp($cfg['db'], $platformDb) !== 0) {
            try {
                $companyIds = $platformCompanyIds;
                if ($companyIds === []) {
                    $companyIds = $this->resolvePlatformCompanyIds($agency, $platformPdo, null, $platformCompanyOverride);
                }
                $report['platform_company_ids'] = $companyIds;
                $report['platform_company_override'] = $platformCompanyOverride;
                $report['platform_company_ids_discovered'] = (int) ($agency['erp_company_id'] ?? 0) < 1 && $companyIds !== [];
                $report['platform_pr_before'] = $this->countPurchaseRequests($platformPdo);
                $report['platform_suppliers_before'] = $this->countPlatformTableRows($platformPdo, 'rateb_suppliers');
                if ($companyIds === []) {
                    if ($report['platform_pr_before'] > 0 || (int) ($report['platform_suppliers_before'] ?? 0) > 0) {
                        $platformErrors[] = __('agency_erp_reset_platform_company_unmatched');
                    }
                } else {
                    $report['platform_pr_by_company_before'] = [];
                    $report['platform_pr_by_company_after'] = [];
                    foreach ($companyIds as $companyId) {
                        $report['platform_pr_by_company_before'][$companyId] = $this->countPurchaseRequests($platformPdo, $companyId);
                        $platformWipes[] = $this->wipePlatformCompanyTenant($platformCfg, $companyId);
                        $after = $this->countPurchaseRequests($platformPdo, $companyId);
                        $report['platform_pr_by_company_after'][$companyId] = $after;
                        if ($after > 0) {
                            $platformErrors[] = 'Platform company #' . $companyId . ' still has ' . $after . ' purchase requests after wipe.';
                        }
                    }
                    $report['platform_pr_after'] = $this->countPurchaseRequests($platformPdo);
                    $primaryPlatformId = (int) ($companyIds[0] ?? 0);
                    if ($primaryPlatformId > 0 && function_exists('rateb_save_agency_erp_company_link')) {
                        rateb_save_agency_erp_company_link($agencyId, $primaryPlatformId);
                        $report['platform_company_linked'] = $primaryPlatformId;
                    }
                }
            } catch (Throwable $e) {
                $platformErrors[] = $e->getMessage();
            }
        }

        if ($platformErrors !== []) {
            $report['platform_warnings'] = $platformErrors;
        }

        $report['agency_id'] = $agencyId;
        $report['agency_name'] = trim((string) ($agency['name'] ?? ''));
        $report['erp_db_name'] = $cfg['db'];
        $report['erp_status'] = $status;
        $report['site_url'] = rtrim(trim((string) ($agency['site_url'] ?? '')), '/');
        $report['site_host'] = $siteHost;
        $report['shell'] = $shell;
        $report['platform_db'] = $platformDb;
        $report['platform_wipes'] = $platformWipes;
        $report['post_reset_counts'] = $this->agencyBusinessRowCounts($pdo, (int) ($shell['company_id'] ?? 0));

        $companyId = (int) ($shell['company_id'] ?? 0);
        if ($companyId > 0) {
            if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
                define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
            }
            Database::useConnectionOverride([
                'db' => $cfg['db'],
                'host' => $cfg['host'],
                'port' => $cfg['port'],
                'user' => $cfg['user'],
                'pass' => $cfg['pass'],
            ]);
            try {
                $report['standard_admin'] = (new DedicatedCompanySeedService())->ensureStandardAdmin($companyId);
            } finally {
                Database::clearConnectionOverride();
            }
        }

        return $report;
    }

    /**
     * Re-provision a ready agency: wipe business data, rebuild shell, reset login to admin / 123456.
     *
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    public function reprovisionAgencyEmpty(array $agency, ?int $platformCompanyOverride = null): array
    {
        $report = $this->resetAgencyData($agency, $platformCompanyOverride);
        $cfg = $this->agencyDatabaseConfig($agency);
        if ($cfg['db'] === '') {
            throw new RuntimeException(__('agency_erp_reset_no_db'));
        }

        if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
            define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
        }
        Database::useConnectionOverride([
            'db' => $cfg['db'],
            'host' => $cfg['host'],
            'port' => $cfg['port'],
            'user' => $cfg['user'],
            'pass' => $cfg['pass'],
        ]);

        try {
            $companyId = (int) (($report['shell']['company_id'] ?? 0));
            $report['standard_admin'] = (new DedicatedCompanySeedService())->ensureStandardAdmin($companyId);
        } finally {
            Database::clearConnectionOverride();
        }

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
        if ($scope === 'all_ready' || $scope === 'all_with_db' || $scope === 'all_subscribed') {
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

        $platformCompanyOverride = (int) ($options['platform_company_id'] ?? 0);
        if ($platformCompanyOverride < 1) {
            $platformCompanyOverride = null;
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
                $perAgencyOverride = $this->resolveResetPlatformCompanyForAgency(
                    $agency,
                    $platformCompanyOverride
                );
                $report = $this->resetAgencyData($agency, $perAgencyOverride);
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
     * Prefer each agency's linked platform company; optional UI override is fallback only.
     *
     * @param array<string, mixed> $agency
     */
    private function resolveResetPlatformCompanyForAgency(array $agency, ?int $globalOverride): ?int
    {
        $linked = (int) ($agency['erp_company_id'] ?? 0);
        if ($linked > 0) {
            return $linked;
        }

        return $globalOverride;
    }

    /**
     * @param array<string, mixed> $agency
     * @param array{host:string,port:int,user:string,pass:string,db:string} $cfg
     * @return array<string, mixed>
     */
    private function rebuildAgencyShellPreserveLogins(array $agency, array $cfg): array
    {
        if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
            define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
        }
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

            return (new \Rateb\App\Services\DedicatedCompanySeedService())->rebuildShellPreserveLogins(
                $companyName,
                $planSlug,
                true
            );
        } finally {
            Database::clearConnectionOverride();
        }
    }

    /**
     * Remove extra companies and orphan tenant rows after agency reset shell rebuild.
     *
     * @return array<string, mixed>
     */
    private function purgeExtraAgencyTenantData(\PDO $pdo, int $keepCompanyId): array
    {
        $report = ['extra_companies_removed' => [], 'orphan_rows_deleted' => []];
        if ($keepCompanyId < 1) {
            return $report;
        }

        try {
            $rows = $pdo->query('SELECT id FROM rateb_companies WHERE id <> ' . (int) $keepCompanyId)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $extraId = (int) ($row['id'] ?? 0);
                if ($extraId < 1) {
                    continue;
                }
                $wipe = (new CompanyTenantWipeService())->wipeCompany($pdo, $extraId, true);
                $report['extra_companies_removed'][] = ['company_id' => $extraId, 'wipe' => $wipe];
            }
        } catch (\Throwable $e) {
            $report['extra_companies_error'] = $e->getMessage();
        }

        $tables = [
            'rateb_inventory',
            'rateb_suppliers',
            'rateb_warehouses',
            'rateb_product_categories',
            'rateb_purchase_requests',
            'rateb_notifications',
            'rateb_support_tickets',
        ];
        foreach ($tables as $table) {
            try {
                $chk = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetch();
                if (!$chk) {
                    continue;
                }
                $stmt = $pdo->prepare(
                    "DELETE FROM {$table} WHERE company_id IS NOT NULL AND company_id > 0 AND company_id <> :cid"
                );
                $stmt->execute(['cid' => $keepCompanyId]);
                $deleted = $stmt->rowCount();
                if ($deleted > 0) {
                    $report['orphan_rows_deleted'][$table] = $deleted;
                }
            } catch (\Throwable $e) {
                $report['orphan_rows_deleted'][$table . '_error'] = $e->getMessage();
            }
        }

        return $report;
    }

    /** @return array<string, int> */
    private function agencyBusinessRowCounts(\PDO $pdo, int $companyId): array
    {
        $counts = [];
        $tables = [
            'inventory' => 'rateb_inventory',
            'suppliers' => 'rateb_suppliers',
            'warehouses' => 'rateb_warehouses',
            'purchase_requests' => 'rateb_purchase_requests',
        ];
        foreach ($tables as $key => $table) {
            try {
                if ($companyId > 0) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE company_id = :cid");
                    $stmt->execute(['cid' => $companyId]);
                } else {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                }
                $counts[$key] = (int) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                $counts[$key] = -1;
            }
        }

        return $counts;
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
