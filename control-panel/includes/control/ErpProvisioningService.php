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
    public static function provision(mysqli $controlConn, array $agency, string $planSlug = ''): array
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
        if ($dbUser === '') {
            $dbUser = defined('DB_USER') ? (string) DB_USER : '';
        }
        if ($dbPass === '' && defined('DB_PASS')) {
            $dbPass = (string) DB_PASS;
        }

        $erpDb = self::resolveErpDatabaseName($agency, $slug, $dbHost, $dbPort, $dbUser, $dbPass);

        self::markStatus($controlConn, $agencyId, 'provisioning', $erpDb, $dbHost, $dbUser, $dbPass);

        try {
            self::ensureErpDatabase($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
            if (self::shouldWipeErpDatabase($agency, $dbHost, $dbPort, $dbUser, $dbPass, $erpDb)) {
                self::wipeErpDatabaseTables($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
            }
            self::ensureDatabaseUtf8mb4($dbHost, $dbPort, $dbUser, $dbPass, $erpDb);
            $migrationLog = self::runErpMigrations($erpDb, $dbHost, $dbPort, $dbUser, $dbPass);
            $seed = self::seedDedicatedCompany($agency, $erpDb, $dbHost, $dbPort, $dbUser, $dbPass, $planSlug);
            self::markStatus($controlConn, $agencyId, 'ready', $erpDb, $dbHost, $dbUser, $dbPass, true);

            return [
                'agency_id' => $agencyId,
                'erp_db_name' => $erpDb,
                'erp_status' => 'ready',
                'erp_plan_slug' => $planSlug,
                'migration_log' => $migrationLog,
                'seed' => $seed,
            ];
        } catch (Throwable $e) {
            self::markStatus($controlConn, $agencyId, 'failed', $erpDb, $dbHost, $dbUser, $dbPass);
            throw $e;
        }
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
        ];
        $afterMap = [
            'erp_db_name' => 'db_name',
            'erp_db_host' => 'erp_db_name',
            'erp_db_user' => 'erp_db_host',
            'erp_db_pass' => 'erp_db_user',
            'erp_status' => 'erp_db_pass',
            'erp_provisioned_at' => 'erp_status',
            'erp_plan_slug' => 'erp_provisioned_at',
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

    /** @param array<string, mixed> $agency */
    private static function resolveErpDatabaseName(
        array $agency,
        string $slug,
        string $host,
        int $port,
        string $user,
        string $pass
    ): string {
        $stored = trim((string) ($agency['erp_db_name'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $existingDb = trim((string) ($agency['db_name'] ?? ''));
        if ($existingDb !== '' && self::canConnectToDatabase($host, $port, $user, $pass, $existingDb)) {
            return $existingDb;
        }

        return function_exists('rateb_suggested_erp_db_name')
            ? rateb_suggested_erp_db_name($slug)
            : ('admin_rateb_erp_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($slug)));
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
        if ($user === '' || $dbName === '') {
            return false;
        }
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->query('SELECT 1');

            return true;
        } catch (Throwable $e) {
            return false;
        }
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
            . 'Create "' . $dbName . '" once in DirectAdmin → MySQL Management, grant "' . $dbUser . '" ALL, then click Provision ERP again.';
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
        string $dbName
    ): bool {
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
        require_once $erpRoot . '/config/app.php';
        require_once $erpRoot . '/app/Core/Database.php';
        require_once $erpRoot . '/app/Core/Model.php';
        require_once $erpRoot . '/app/Core/TenantContext.php';
        require_once $erpRoot . '/app/models/Company.php';
        require_once $erpRoot . '/app/models/Entities.php';
        require_once $erpRoot . '/app/models/Plan.php';
        require_once $erpRoot . '/app/services/PlanLimitService.php';
        require_once $erpRoot . '/app/services/AuthorizationService.php';
        require_once $erpRoot . '/app/services/BarcodeLoginService.php';
        require_once $erpRoot . '/app/services/BranchService.php';
        require_once $erpRoot . '/app/services/DedicatedTenantPolicy.php';
        require_once $erpRoot . '/app/services/DedicatedCompanySeedService.php';

        if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
            define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
        }

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
}
