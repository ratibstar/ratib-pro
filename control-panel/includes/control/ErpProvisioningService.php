<?php
/**
 * EN: Provisions a dedicated RATEB ERP database per control_agencies row.
 * AR: يجهّز قاعدة RATEB ERP مخصصة لكل سجل في control_agencies.
 */
declare(strict_types=1);

final class ErpProvisioningService
{
    /** @return list<string> */
    public static function allowedPlanSlugs(): array
    {
        return ['starter', 'professional', 'enterprise'];
    }

    public static function normalizePlanSlug(string $raw): string
    {
        $slug = strtolower(trim($raw));
        if ($slug === '') {
            return 'professional';
        }

        return in_array($slug, self::allowedPlanSlugs(), true) ? $slug : 'professional';
    }

    /** @param array<string, mixed> $agency */
    public static function resolvePlanSlug(array $agency, string $override = ''): string
    {
        $fromOverride = trim($override);
        if ($fromOverride !== '') {
            return self::normalizePlanSlug($fromOverride);
        }
        $stored = trim((string) ($agency['erp_plan_slug'] ?? ''));

        return self::normalizePlanSlug($stored);
    }

    public static function saveAgencyErpCompanyId(mysqli $controlConn, int $agencyId, int $companyId): void
    {
        if ($agencyId < 1) {
            throw new InvalidArgumentException('agency id is required');
        }
        self::ensureErpColumns($controlConn);
        if ($companyId > 0) {
            $stmt = $controlConn->prepare('UPDATE control_agencies SET erp_company_id = ? WHERE id = ?');
            if (!$stmt) {
                throw new RuntimeException('Failed to save ERP company link');
            }
            $stmt->bind_param('ii', $companyId, $agencyId);
            $stmt->execute();
            $stmt->close();

            return;
        }
        $stmt = $controlConn->prepare('UPDATE control_agencies SET erp_company_id = NULL WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('Failed to clear ERP company link');
        }
        $stmt->bind_param('i', $agencyId);
        $stmt->execute();
        $stmt->close();
    }

    public static function saveAgencyPlan(mysqli $controlConn, int $agencyId, string $planSlug): string
    {
        if ($agencyId < 1) {
            throw new InvalidArgumentException('agency id is required');
        }
        self::ensureErpColumns($controlConn);
        $planSlug = self::normalizePlanSlug($planSlug);
        $stmt = $controlConn->prepare('UPDATE control_agencies SET erp_plan_slug = ? WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('Failed to save ERP plan');
        }
        $stmt->bind_param('si', $planSlug, $agencyId);
        $stmt->execute();
        $stmt->close();

        return $planSlug;
    }

    /**
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    public static function provision(mysqli $controlConn, array $agency, string $planSlug = '', bool $force = false): array
    {
        $agencyId = (int) ($agency['id'] ?? 0);
        if ($agencyId < 1) {
            throw new InvalidArgumentException('agency id is required');
        }

        self::ensureErpColumns($controlConn);
        $planSlug = self::saveAgencyPlan($controlConn, $agencyId, self::resolvePlanSlug($agency, $planSlug));

        $slug = trim((string) ($agency['slug'] ?? ''));
        if ($slug === '') {
            $slug = 'agency-' . $agencyId;
        }

        $target = self::resolveErpTarget($agency, $slug);
        $erpDb = $target['db'];
        $dbHost = $target['host'];
        $dbPort = $target['port'];
        $dbUser = $target['user'];
        $dbPass = $target['pass'];

        self::markStatus($controlConn, $agencyId, 'provisioning', $erpDb, $dbHost, $dbUser, $dbPass);

        try {
            $currentStatus = strtolower(trim((string) ($agency['erp_status'] ?? 'none')));
            $hasErpDb = trim((string) ($agency['erp_db_name'] ?? '')) !== '';
            if ($force && $currentStatus === 'ready' && $hasErpDb) {
                return self::softReprovisionReady(
                    $controlConn,
                    $agency,
                    $agencyId,
                    $planSlug,
                    $erpDb,
                    $dbHost,
                    $dbPort,
                    $dbUser,
                    $dbPass
                );
            }

            self::ensureErpDatabase($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
            if (self::shouldWipeErpDatabase($agency, $dbHost, $dbPort, $dbUser, $dbPass, $erpDb, $force)) {
                self::wipeErpDatabaseTables($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
            }
            self::ensureDatabaseUtf8mb4($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
            self::ensureAllTablesUtf8mb4($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
            $migrationLog = self::runErpMigrations($erpDb, $dbHost, $dbPort, $dbUser, $dbPass);
            $seed = self::seedDedicatedCompany($agency, $erpDb, $dbHost, $dbPort, $dbUser, $dbPass, $planSlug);
            $agency['erp_plan_slug'] = $planSlug;
            $agency['erp_db_name'] = $erpDb;
            $agency['erp_db_host'] = $dbHost;
            $agency['erp_db_user'] = $dbUser;
            $agency['erp_db_pass'] = $dbPass;
            $agency['db_port'] = $dbPort;
            $planApply = self::applyPlanToAgencyErp($agency, $planSlug);
            self::markStatus($controlConn, $agencyId, 'ready', $erpDb, $dbHost, $dbUser, $dbPass, true);
            self::activatePlatformCompanyAfterProvision($agency);

            return [
                'agency_id' => $agencyId,
                'erp_db_name' => $erpDb,
                'erp_status' => 'ready',
                'erp_plan_slug' => $planSlug,
                'migration_log' => $migrationLog,
                'seed' => $seed,
                'plan_apply' => $planApply,
            ];
        } catch (Throwable $e) {
            self::markStatus($controlConn, $agencyId, 'failed', $erpDb, $dbHost, $dbUser, $dbPass);
            throw $e;
        }
    }

    /**
     * Ready agency re-provision: apply migrations, wipe business data, standard admin / 123456.
     *
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    private static function softReprovisionReady(
        mysqli $controlConn,
        array $agency,
        int $agencyId,
        string $planSlug,
        string $erpDb,
        string $dbHost,
        int $dbPort,
        string $dbUser,
        string $dbPass
    ): array {
        $agency['erp_plan_slug'] = $planSlug;
        $agency['erp_db_name'] = $erpDb;
        $agency['erp_db_host'] = $dbHost;
        $agency['erp_db_user'] = $dbUser;
        $agency['erp_db_pass'] = $dbPass;
        $agency['db_port'] = $dbPort;

        self::ensureErpDatabase($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
        self::ensureDatabaseUtf8mb4($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
        self::ensureAllTablesUtf8mb4($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
        $migrationLog = self::runErpMigrations($erpDb, $dbHost, $dbPort, $dbUser, $dbPass);
        $emptyReport = self::emptyAgencyForReprovision($agency);
        $companyId = (int) (($emptyReport['shell']['company_id'] ?? 0));
        if ($companyId > 0) {
            self::saveAgencyErpCompanyId($controlConn, $agencyId, $companyId);
        }
        $planApply = self::applyPlanToAgencyErp($agency, $planSlug);
        self::markStatus($controlConn, $agencyId, 'ready', $erpDb, $dbHost, $dbUser, $dbPass, true);
        self::activatePlatformCompanyAfterProvision($agency);

        return [
            'agency_id' => $agencyId,
            'erp_db_name' => $erpDb,
            'erp_status' => 'ready',
            'erp_plan_slug' => $planSlug,
            'reprovision_mode' => 'empty_migrations',
            'migration_log' => $migrationLog,
            'empty_report' => $emptyReport,
            'standard_admin' => $emptyReport['standard_admin'] ?? null,
            'plan_apply' => $planApply,
        ];
    }

    /**
     * Push control-panel ERP package into the dedicated company row (modules / plan_id).
     * Applies to every connectable ERP DB candidate for the agency (prevents wrong-DB drift
     * between control_agencies.erp_db_name and the host pin used by admin.*.rateb.sa).
     *
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    public static function applyPlanToAgencyErp(array $agency, string $planSlug): array
    {
        $erpRoot = self::erpRootPath();
        self::bootstrapErpForSeed($erpRoot);
        $lookup = dirname(__DIR__, 2) . '/../config/env/agency_lookup.php';
        if (!is_file($lookup)) {
            $lookup = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
        }
        if (is_file($lookup)) {
            require_once $lookup;
        }
        if (!is_file($erpRoot . '/app/services/AgencyErpMigrationService.php')) {
            throw new RuntimeException('AgencyErpMigrationService missing');
        }
        require_once $erpRoot . '/app/services/AgencyErpMigrationService.php';

        $planSlug = self::normalizePlanSlug($planSlug);
        $agencyId = (int) ($agency['id'] ?? 0);
        $slug = trim((string) ($agency['slug'] ?? ''));
        if ($slug === '') {
            $slug = 'agency-' . ($agencyId > 0 ? $agencyId : 'x');
        }

        $siteHost = '';
        if (function_exists('rateb_agency_host_from_site_url')) {
            $siteHost = rateb_agency_host_from_site_url(trim((string) ($agency['site_url'] ?? '')));
        }
        if ($siteHost === '') {
            $siteHost = strtolower(trim((string) ($agency['slug'] ?? '')));
            if ($siteHost !== '' && !str_contains($siteHost, '.')) {
                $siteHost = $siteHost . '.rateb.sa';
            }
        }

        $cred = self::resolveAgencyMysqlCredentials($agency);
        $candidates = [];
        $pushCandidate = static function (array $row) use (&$candidates): void {
            $db = trim((string) ($row['db'] ?? ''));
            if ($db === '') {
                return;
            }
            $key = strtolower($db);
            if (isset($candidates[$key])) {
                return;
            }
            $candidates[$key] = [
                'db' => $db,
                'host' => (string) ($row['host'] ?? 'localhost'),
                'port' => (int) ($row['port'] ?? 3306),
                'user' => (string) ($row['user'] ?? ''),
                'pass' => (string) ($row['pass'] ?? ''),
            ];
        };

        try {
            $pushCandidate(self::resolveErpTarget($agency, $slug));
        } catch (\Throwable $e) {
            // continue with other candidates
        }

        if ($siteHost !== '' && function_exists('rateb_agency_erp_binding_for_host')) {
            $binding = rateb_agency_erp_binding_for_host($siteHost);
            if (is_array($binding)) {
                $pushCandidate([
                    'db' => (string) ($binding['db'] ?? ''),
                    'host' => (string) ($binding['host'] ?? $cred['host']),
                    'port' => (int) ($binding['port'] ?? $cred['port']),
                    'user' => (string) ($binding['user'] ?? $cred['user']),
                    'pass' => (string) ($binding['pass'] ?? $cred['pass']),
                ]);
            }
        }

        foreach ([
            trim((string) ($agency['erp_db_name'] ?? '')),
            trim((string) ($agency['db_name'] ?? '')),
        ] as $named) {
            if ($named !== '') {
                $pushCandidate([
                    'db' => $named,
                    'host' => $cred['host'],
                    'port' => $cred['port'],
                    'user' => $cred['user'],
                    'pass' => $cred['pass'],
                ]);
            }
        }

        // Host pin used by config/env/admin_rateb_sa.php when lookup is unavailable.
        if ($siteHost === 'admin.rateb.sa' || (int) ($agency['id'] ?? 0) === 34) {
            $pushCandidate([
                'db' => 'admin_admin-rateb',
                'host' => $cred['host'],
                'port' => $cred['port'],
                'user' => $cred['user'],
                'pass' => $cred['pass'],
            ]);
        }

        if ($candidates === []) {
            throw new RuntimeException('No ERP database candidates for agency #' . $agencyId);
        }

        $results = [];
        $errors = [];
        $primary = null;
        foreach ($candidates as $candidate) {
            $ping = self::resolveWorkingConnection(
                (string) $candidate['host'],
                (int) $candidate['port'],
                (string) $candidate['user'],
                (string) $candidate['pass'],
                (string) $candidate['db']
            );
            if ($ping === null) {
                $errors[] = 'Cannot connect to ' . $candidate['db'];
                continue;
            }

            $agencyCopy = $agency;
            $agencyCopy['erp_plan_slug'] = $planSlug;
            $agencyCopy['erp_db_name'] = $ping['db'];
            $agencyCopy['erp_db_host'] = $ping['host'];
            $agencyCopy['erp_db_user'] = $ping['user'];
            $agencyCopy['erp_db_pass'] = $ping['pass'];
            $agencyCopy['db_host'] = $ping['host'];
            $agencyCopy['db_port'] = $ping['port'];
            $agencyCopy['db_user'] = $ping['user'];
            $agencyCopy['db_pass'] = $ping['pass'];
            $agencyCopy['db_name'] = $ping['db'];

            try {
                $applied = (new \Rateb\App\Services\AgencyErpMigrationService())->applyDedicatedCompanyPlan(
                    $agencyCopy,
                    $planSlug
                );
                $applied['erp_db_name'] = $ping['db'];
                $applied['erp_db_host'] = $ping['host'];
                $mods = is_array($applied['modules'] ?? null) ? $applied['modules'] : [];
                $verified = !empty($applied['verified']) && in_array('hr', $mods, true);
                if ($mods === []
                    || (!in_array('hr', $mods, true) && in_array($planSlug, ['professional', 'enterprise'], true))
                    || !in_array('access_control', $mods, true)
                ) {
                    $errors[] = $ping['db'] . ': modules incomplete (' . implode(',', $mods) . ')';
                    continue;
                }
                $results[] = $applied;
                if ($ping['db'] === 'admin_admin-rateb' || $verified) {
                    $primary = $applied;
                }
                if ($primary === null) {
                    $primary = $applied;
                }
            } catch (\Throwable $e) {
                $errors[] = $ping['db'] . ': ' . $e->getMessage();
            }
        }

        if ($primary === null) {
            throw new RuntimeException(
                'Plan apply failed for agency #' . $agencyId
                . ' (' . $planSlug . '): ' . implode(' | ', $errors)
            );
        }

        $primary['applied_databases'] = array_values(array_map(
            static fn (array $r): string => (string) ($r['erp_db_name'] ?? ''),
            $results
        ));
        $primary['apply_errors'] = $errors;
        $primary['site_host'] = $siteHost;

        return $primary;
    }

    /**
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    private static function emptyAgencyForReprovision(array $agency): array
    {
        $erpRoot = self::erpRootPath();
        self::bootstrapErpForSeed($erpRoot);
        $lookup = dirname(__DIR__, 2) . '/../config/env/agency_lookup.php';
        if (!is_file($lookup)) {
            $lookup = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
        }
        if (is_file($lookup)) {
            require_once $lookup;
        }
        if (!is_file($erpRoot . '/app/services/AgencyErpMigrationService.php')) {
            throw new RuntimeException('AgencyErpMigrationService missing');
        }
        require_once $erpRoot . '/app/services/AgencyErpMigrationService.php';

        $override = (int) ($agency['erp_company_id'] ?? 0);
        $platformOverride = $override > 0 ? $override : null;

        return (new \Rateb\App\Services\AgencyErpMigrationService())->reprovisionAgencyEmpty(
            $agency,
            $platformOverride
        );
    }

    public static function ensureErpColumns(mysqli $controlConn): void
    {
        $columns = [
            'erp_db_name' => 'VARCHAR(64) NULL',
            'erp_db_host' => 'VARCHAR(255) NULL',
            'erp_db_user' => 'VARCHAR(64) NULL',
            'erp_db_pass' => 'VARCHAR(255) NULL',
            "erp_status" => "ENUM('none','provisioning','ready','failed') NOT NULL DEFAULT 'none'",
            'erp_provisioned_at' => 'DATETIME NULL',
            "erp_plan_slug" => "VARCHAR(32) NOT NULL DEFAULT 'professional'",
            'erp_company_id' => 'INT UNSIGNED NULL',
        ];
        $afterMap = [
            'erp_db_name' => 'db_name',
            'erp_db_host' => 'erp_db_name',
            'erp_db_user' => 'erp_db_host',
            'erp_db_pass' => 'erp_db_user',
            'erp_status' => 'erp_db_pass',
            'erp_provisioned_at' => 'erp_status',
            'erp_plan_slug' => 'erp_provisioned_at',
            'erp_company_id' => 'erp_plan_slug',
        ];
        foreach ($columns as $name => $definition) {
            $res = @$controlConn->query("SHOW COLUMNS FROM control_agencies LIKE '" . $controlConn->real_escape_string($name) . "'");
            if ($res && $res->num_rows > 0) {
                continue;
            }
            $after = $afterMap[$name] ?? null;
            $sql = 'ALTER TABLE control_agencies ADD COLUMN `' . $name . '` ' . $definition;
            if ($after !== null) {
                $sql .= ' AFTER `' . $after . '`';
            }
            @$controlConn->query($sql);
        }
    }

    private static function ensureAgencyDbHelper(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/api/control/agency-db-helper.php';
        if (is_file($path)) {
            require_once $path;
        }
        $loaded = true;
    }

    /**
     * Match getAgencyDbConnection: shared MySQL user uses .env password, not stale agency row.
     *
     * @param array<string, mixed> $agency
     * @return array{host: string, port: int, user: string, pass: string}
     */
    private static function resolveAgencyMysqlCredentials(array $agency): array
    {
        $host = trim((string) ($agency['erp_db_host'] ?? ''));
        if ($host === '') {
            $host = trim((string) ($agency['db_host'] ?? ''));
        }
        if ($host === '') {
            $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        }
        $port = (int) ($agency['db_port'] ?? (defined('DB_PORT') ? (int) DB_PORT : 3306));
        $user = trim((string) ($agency['erp_db_user'] ?? ''));
        if ($user === '') {
            $user = trim((string) ($agency['db_user'] ?? ''));
        }
        if ($user === '') {
            $user = defined('DB_USER') ? (string) DB_USER : '';
        }
        $agencyPass = (string) ($agency['erp_db_pass'] ?? '');
        if ($agencyPass === '') {
            $agencyPass = (string) ($agency['db_pass'] ?? '');
        }
        $envUser = defined('DB_USER') ? (string) DB_USER : '';
        $envPass = defined('DB_PASS') ? (string) DB_PASS : '';
        $pass = ($user !== '' && $user === $envUser) ? $envPass : ($agencyPass !== '' ? $agencyPass : $envPass);

        return [
            'host' => $host,
            'port' => $port > 0 ? $port : 3306,
            'user' => $user,
            'pass' => $pass,
        ];
    }

    /**
     * @param array<string, mixed> $agency
     * @return array{db: string, host: string, port: int, user: string, pass: string}
     */
    private static function resolveErpTarget(array $agency, string $slug): array
    {
        $cred = self::resolveAgencyMysqlCredentials($agency);
        $host = $cred['host'];
        $port = $cred['port'];
        $user = $cred['user'];
        $pass = $cred['pass'];

        $tryDb = static function (string $dbName) use ($host, $port, $user, $pass): ?array {
            return self::resolveWorkingConnection($host, $port, $user, $pass, $dbName);
        };

        $stored = trim((string) ($agency['erp_db_name'] ?? ''));
        if ($stored !== '') {
            $hit = $tryDb($stored);
            if ($hit !== null) {
                return $hit;
            }
        }

        self::ensureAgencyDbHelper();
        if (function_exists('getAgencyDbConnection')) {
            $countryId = (int) ($agency['country_id'] ?? 0);
            $info = getAgencyDbConnection($agency, $countryId);
            if (is_array($info) && trim((string) ($info['db_name'] ?? '')) !== '') {
                if (isset($info['conn']) && $info['conn'] instanceof mysqli) {
                    $info['conn']->close();
                }

                return [
                    'db' => (string) $info['db_name'],
                    'host' => (string) ($info['connect_host'] ?? $host),
                    'port' => (int) ($info['connect_port'] ?? $port),
                    'user' => (string) ($info['connect_user'] ?? $user),
                    'pass' => (string) ($info['connect_pass'] ?? $pass),
                ];
            }
        }

        $tenantDb = trim((string) ($agency['db_name'] ?? ''));
        if ($tenantDb !== '') {
            $hit = $tryDb($tenantDb);
            if ($hit !== null) {
                return $hit;
            }
        }

        $suggested = function_exists('rateb_suggested_erp_db_name')
            ? rateb_suggested_erp_db_name($slug)
            : ('admin_rateb_erp_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($slug)));
        $hit = $tryDb($suggested);
        if ($hit !== null) {
            return $hit;
        }

        return [
            'db' => $suggested,
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
        ];
    }

    /**
     * @return array{db: string, host: string, port: int, user: string, pass: string}|null
     */
    private static function resolveWorkingConnection(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $dbName
    ): ?array {
        if ($user === '' || $dbName === '') {
            return null;
        }
        $attempts = [];
        $add = static function (string $h, string $u, string $p) use (&$attempts): void {
            $key = $h . "\0" . $u . "\0" . $p;
            $attempts[$key] = ['host' => $h, 'user' => $u, 'pass' => $p];
        };
        $add($host, $user, $pass);
        if ($host === 'localhost') {
            $add('127.0.0.1', $user, $pass);
        } elseif ($host === '127.0.0.1') {
            $add('localhost', $user, $pass);
        }
        if (defined('DB_USER') && defined('DB_PASS')) {
            $envUser = (string) DB_USER;
            $envPass = (string) DB_PASS;
            $add($host, $envUser, $envPass);
            if ($host === 'localhost') {
                $add('127.0.0.1', $envUser, $envPass);
            } elseif ($host === '127.0.0.1') {
                $add('localhost', $envUser, $envPass);
            }
        }

        foreach ($attempts as $a) {
            if (!self::pdoPing($a['host'], $port, $a['user'], $a['pass'], $dbName)) {
                continue;
            }

            return [
                'db' => $dbName,
                'host' => $a['host'],
                'port' => $port,
                'user' => $a['user'],
                'pass' => $a['pass'],
            ];
        }

        return null;
    }

    private static function pdoPing(string $host, int $port, string $user, string $pass, string $dbName): bool
    {
        try {
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            if (defined('PDO::ATTR_TIMEOUT')) {
                $options[PDO::ATTR_TIMEOUT] = 3;
            }
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
            $pdo = new PDO($dsn, $user, $pass, $options);
            $pdo->query('SELECT 1');

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function ensureErpDatabase(string $host, int $port, string $user, string $pass, string $dbName): void
    {
        if (self::canConnectToDatabase($host, $port, $user, $pass, $dbName)) {
            return;
        }

        $createError = null;
        $candidates = self::mysqlAdminCredentialCandidates($user, $pass);
        foreach ($candidates as $cred) {
            try {
                $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
                $pdo = new PDO($dsn, $cred['user'], $cred['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec(
                    'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
                if (self::canConnectToDatabase($host, $port, $user, $pass, $dbName)) {
                    return;
                }
            } catch (Throwable $e) {
                $createError = $e->getMessage();
                error_log('ErpProvisioningService create-db attempt for ' . $dbName . ': ' . $createError);
            }
        }

        if (self::tryDirectAdminCreateDatabase($dbName, $user, $pass) && self::canConnectToDatabase($host, $port, $user, $pass, $dbName)) {
            return;
        }

        if (self::tryDirectAdminApiCreateDatabase($dbName, $user, $pass) && self::canConnectToDatabase($host, $port, $user, $pass, $dbName)) {
            return;
        }

        if (self::canConnectToDatabase($host, $port, $user, $pass, $dbName)) {
            return;
        }

        throw new RuntimeException(self::databaseAccessHelpMessage($dbName, $user, $createError));
    }

    /** @return list<array{user: string, pass: string}> */
    private static function mysqlAdminCredentialCandidates(string $agencyUser, string $agencyPass): array
    {
        $out = [];
        $seen = [];
        $add = static function (string $user, string $pass) use (&$out, &$seen): void {
            if ($user === '') {
                return;
            }
            $key = $user . "\0" . $pass;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = ['user' => $user, 'pass' => $pass];
        };

        $add($agencyUser, $agencyPass);
        if (defined('DB_USER')) {
            $add((string) DB_USER, defined('DB_PASS') ? (string) DB_PASS : '');
        }
        if (defined('CONTROL_DB_USER')) {
            $add((string) CONTROL_DB_USER, defined('CONTROL_DB_PASS') ? (string) CONTROL_DB_PASS : '');
        }

        return $out;
    }

    private static function canConnectToDatabase(string $host, int $port, string $user, string $pass, string $dbName): bool
    {
        return self::resolveWorkingConnection($host, $port, $user, $pass, $dbName) !== null;
    }

    private static function tryDirectAdminCreateDatabase(string $dbName, string $dbUser = '', string $dbPass = ''): bool
    {
        $script = '/usr/local/directadmin/scripts/create_database.sh';
        if (!is_executable($script)) {
            return false;
        }
        $daUser = getenv('RATEB_DA_LINUX_USER') ?: 'admin';
        $cmd = escapeshellarg($script) . ' ' . escapeshellarg($daUser) . ' ' . escapeshellarg($dbName);
        if ($dbPass !== '') {
            $cmd .= ' ' . escapeshellarg($dbPass);
        }
        $cmd .= ' 2>&1';
        $output = [];
        $exitCode = 1;
        @exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            error_log('ErpProvisioningService DA create_database failed for ' . $dbName . ': ' . implode("\n", $output));

            return false;
        }

        return true;
    }

    private static function tryDirectAdminApiCreateDatabase(string $dbName, string $dbUser, string $dbPass): bool
    {
        $da = '/usr/local/directadmin/directadmin';
        if (!is_executable($da) || $dbUser === '') {
            return false;
        }
        $daLinuxUser = getenv('RATEB_DA_LINUX_USER') ?: 'admin';
        $shortName = $dbName;
        $userPrefix = $dbUser . '_';
        if (str_starts_with($dbName, $userPrefix)) {
            $shortName = substr($dbName, strlen($userPrefix));
        } elseif (str_starts_with($dbName, 'admin_')) {
            $shortName = substr($dbName, 6);
        }
        if ($shortName === '') {
            return false;
        }
        $pass = $dbPass !== '' ? $dbPass : bin2hex(random_bytes(12));
        $cmd = escapeshellarg($da) . ' api --user=' . escapeshellarg($daLinuxUser)
            . ' CMD_API_DATABASES action=create name=' . escapeshellarg($shortName)
            . ' user=' . escapeshellarg($dbUser)
            . ' passwd=' . escapeshellarg($pass)
            . ' passwd2=' . escapeshellarg($pass) . ' 2>&1';
        $output = [];
        $exitCode = 1;
        @exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            error_log('ErpProvisioningService DA API create failed for ' . $dbName . ': ' . implode("\n", $output));

            return false;
        }

        return true;
    }

    private static function databaseAccessHelpMessage(string $dbName, string $dbUser, ?string $createError): string
    {
        $hint = 'Control Panel could not create the ERP database automatically on this host. '
            . 'If this agency already has a tenant database (DB Name column), click Provision ERP again after deploy — it will reuse that database when reachable. '
            . 'Otherwise create "' . $dbName . '" once in DirectAdmin → MySQL Management, grant "' . $dbUser . '" ALL, then click Provision ERP again.';
        if ($createError !== null && $createError !== '') {
            return $createError . '. ' . $hint;
        }

        return 'ERP database is not accessible. ' . $hint;
    }

    /** @param array<string, mixed> $agency */
    private static function shouldWipeErpDatabase(
        array $agency,
        string $host,
        int $port,
        string $user,
        string $pass,
        string $dbName,
        bool $force = false
    ): bool {
        if ($force) {
            return true;
        }
        $status = strtolower(trim((string) ($agency['erp_status'] ?? 'none')));
        if (in_array($status, ['failed', 'provisioning'], true)) {
            return true;
        }

        return self::erpDatabaseHasPartialSchema($host, $port, $user, $pass, $dbName);
    }

    private static function erpDatabaseHasPartialSchema(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $dbName
    ): bool {
        if (!self::canConnectToDatabase($host, $port, $user, $pass, $dbName)) {
            return false;
        }
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if ($tables === [] || $tables === false) {
                return false;
            }
            $migrationCount = 0;
            if (in_array('rateb_migrations', $tables, true)) {
                $migrationCount = (int) $pdo->query('SELECT COUNT(*) FROM rateb_migrations')->fetchColumn();
            }

            return $migrationCount > 0 && $migrationCount < 40;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function wipeErpDatabaseTables(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $dbName
    ): void {
        if (!self::canConnectToDatabase($host, $port, $user, $pass, $dbName)) {
            return;
        }
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
        }
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if (is_array($tables)) {
            foreach ($tables as $table) {
                $table = (string) $table;
                if ($table === '') {
                    continue;
                }
                $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private static function ensureAllTablesUtf8mb4(string $host, int $port, string $user, string $pass, string $dbName): void
    {
        if ($dbName === '' || !self::canConnectToDatabase($host, $port, $user, $pass, $dbName)) {
            return;
        }
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
            }
            $pdo = new PDO($dsn, $user, $pass, $options);
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($tables)) {
                return;
            }
            foreach ($tables as $table) {
                $table = (string) $table;
                if ($table === '') {
                    continue;
                }
                $pdo->exec(
                    'ALTER TABLE `' . str_replace('`', '``', $table) . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
            }
        } catch (Throwable $e) {
            error_log('ErpProvisioningService ensureAllTablesUtf8mb4: ' . $e->getMessage());
        }
    }

    private static function ensureDatabaseUtf8mb4(string $host, int $port, string $user, string $pass, string $dbName): void
    {
        if ($dbName === '') {
            return;
        }
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec(
                'ALTER DATABASE `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            error_log('ErpProvisioningService ensureDatabaseUtf8mb4: ' . $e->getMessage());
        }
    }

    /** @return array<int, string> */
    private static function runErpMigrations(string $dbName, string $host, int $port, string $user, string $pass): array
    {
        $erpRoot = self::erpRootPath();
        if (!is_file($erpRoot . '/app/services/MigrationService.php')) {
            throw new RuntimeException('rateb-erp not found on server');
        }
        if (!defined('RATEB_ROOT')) {
            define('RATEB_ROOT', $erpRoot);
        }
        if (!defined('RATEB_ENV_NO_SESSION')) {
            define('RATEB_ENV_NO_SESSION', true);
        }
        require_once $erpRoot . '/app/Core/Database.php';
        require_once $erpRoot . '/app/services/MigrationService.php';

        return (new \Rateb\App\Services\MigrationService())->runAllForDatabase([
            'db' => $dbName,
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
        ]);
    }

    /**
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    private static function seedDedicatedCompany(
        array $agency,
        string $dbName,
        string $host,
        int $port,
        string $user,
        string $pass,
        string $planSlug
    ): array {
        $erpRoot = self::erpRootPath();
        self::bootstrapErpForSeed($erpRoot);

        \Rateb\App\Core\Database::useConnectionOverride([
            'db' => $dbName,
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
        ]);

        try {
            $companyName = trim((string) ($agency['name'] ?? 'Company'));
            $email = self::adminEmailForAgency($agency);

            return (new \Rateb\App\Services\DedicatedCompanySeedService())->seed(
                $companyName,
                $email,
                $planSlug,
                $companyName
            );
        } finally {
            \Rateb\App\Core\Database::clearConnectionOverride();
        }
    }

    private static function bootstrapErpForSeed(string $erpRoot): void
    {
        if (!defined('RATEB_ENV_NO_SESSION')) {
            define('RATEB_ENV_NO_SESSION', true);
        }
        if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
            define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
        }
        if (!class_exists(\Rateb\App\Core\Bootstrap::class, false)) {
            require_once $erpRoot . '/app/Core/Bootstrap.php';
        }
        \Rateb\App\Core\Bootstrap::initMinimal($erpRoot);
    }

    /** @param array<string, mixed> $agency */
    private static function adminEmailForAgency(array $agency): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '', strtolower((string) ($agency['slug'] ?? 'client')));
        if ($slug === '') {
            $slug = 'client' . (int) ($agency['id'] ?? 0);
        }

        return 'admin+' . $slug . '@rateb.sa';
    }

    private static function erpRootPath(): string
    {
        $candidates = [
            dirname(__DIR__, 3) . '/rateb-erp',
            dirname(__DIR__, 4) . '/rateb-erp',
        ];
        $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($docRoot !== '') {
            $candidates[] = rtrim($docRoot, '/\\') . '/rateb-erp';
        }
        foreach ($candidates as $path) {
            if (is_file($path . '/public/index.php')) {
                return str_replace('\\', '/', realpath($path) ?: $path);
            }
        }

        return str_replace('\\', '/', $candidates[0]);
    }

    /** Platform company row is SaaS oversight; Provision ERP is the approval. */
    private static function activatePlatformCompanyAfterProvision(array $agency): void
    {
        $agency['erp_status'] = 'ready';
        try {
            if (class_exists(\Rateb\App\Core\Database::class)) {
                \Rateb\App\Core\Database::clearConnectionOverride();
            }
            $mig = new \Rateb\App\Services\AgencyErpMigrationService();
            $mig->activateProvisionedPlatformCompanies();
            $mig->ensurePlatformCompanyForAgency($agency);
        } catch (Throwable $e) {
            error_log('activatePlatformCompanyAfterProvision: ' . $e->getMessage());
        }
    }

    private static function markStatus(
        mysqli $controlConn,
        int $agencyId,
        string $status,
        string $erpDb,
        string $dbHost,
        string $dbUser,
        string $dbPass,
        bool $setProvisionedAt = false
    ): void {
        $sql = 'UPDATE control_agencies SET erp_db_name = ?, erp_db_host = ?, erp_db_user = ?, erp_db_pass = ?, erp_status = ?';
        if ($setProvisionedAt) {
            $sql .= ', erp_provisioned_at = NOW()';
        }
        $sql .= ' WHERE id = ?';
        $stmt = $controlConn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to update agency ERP status');
        }
        $stmt->bind_param('sssssi', $erpDb, $dbHost, $dbUser, $dbPass, $status, $agencyId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * @param array<string, mixed> $agency
     * @return array{host:string,port:int,user:string,pass:string,db:string}
     */
    public static function agencyDatabaseConfig(array $agency): array
    {
        $cred = self::resolveAgencyMysqlCredentials($agency);
        $dbName = trim((string) ($agency['erp_db_name'] ?? ''));
        if ($dbName === '') {
            $dbName = trim((string) ($agency['db_name'] ?? ''));
        }

        return [
            'host' => $cred['host'] !== '' ? $cred['host'] : 'localhost',
            'port' => $cred['port'],
            'user' => $cred['user'],
            'pass' => $cred['pass'],
            'db' => $dbName,
        ];
    }

    /**
     * Apply pending rateb-erp SQL migrations to one agency ERP database.
     *
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    public static function runMigrationsForAgency(array $agency): array
    {
        $agencyId = (int) ($agency['id'] ?? 0);
        $cfg = self::agencyDatabaseConfig($agency);
        if ($cfg['db'] === '') {
            throw new RuntimeException('No ERP database configured for agency #' . $agencyId);
        }
        if (!self::canConnectToDatabase($cfg['host'], $cfg['port'], $cfg['user'], $cfg['pass'], $cfg['db'])) {
            throw new RuntimeException('Cannot connect to ERP database ' . $cfg['db']);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $log = self::runErpMigrations($cfg['db'], $cfg['host'], $cfg['port'], $cfg['user'], $cfg['pass']);

        return [
            'agency_id' => $agencyId,
            'agency_name' => trim((string) ($agency['name'] ?? '')),
            'erp_db_name' => $cfg['db'],
            'log' => $log,
        ];
    }

    /**
     * Agencies with a dedicated ERP database (ready / failed retry).
     *
     * @return list<array<string, mixed>>
     */
    public static function listErpAgencies(mysqli $controlConn, bool $subscribedOnly = false): array
    {
        self::ensureErpColumns($controlConn);
        $hasSusp = false;
        $chk = @$controlConn->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
        if ($chk && $chk->num_rows > 0) {
            $hasSusp = true;
        }
        $sql = 'SELECT id, name, slug, site_url, erp_db_name, erp_status, erp_plan_slug, is_active';
        if ($hasSusp) {
            $sql .= ', is_suspended';
        }
        $sql .= " FROM control_agencies WHERE TRIM(COALESCE(erp_db_name, '')) <> ''";
        if ($subscribedOnly) {
            $sql .= ' AND is_active = 1';
            if ($hasSusp) {
                $sql .= ' AND COALESCE(is_suspended, 0) = 0';
            }
            $sql .= " AND LOWER(COALESCE(erp_status, 'none')) = 'ready'";
        }
        $sql .= ' ORDER BY name ASC';
        $res = $controlConn->query($sql);
        if (!$res) {
            return [];
        }
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }
}
