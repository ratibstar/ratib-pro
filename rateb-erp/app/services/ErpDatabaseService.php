<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

final class ErpDatabaseService
{
    /** @return array<string, mixed> */
    public function diagnoseErp(): array
    {
        try {
            $pdo = Database::connection();
            $db = Database::resolvedDatabaseName();
            return array_merge(['ok' => true, 'db' => $db], $this->stats($pdo));
        } catch (\Throwable $e) {
            return ['ok' => false, 'db' => $this->expectedErpDbName(), 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    public function diagnoseControlPanel(): array
    {
        $name = $this->controlPanelDbName();
        try {
            $pdo = $this->openDatabase($name);
            $stats = $this->stats($pdo);
            $stats['warning'] = ($stats['rateb_tables'] ?? 0) > 0
                ? 'ERP tables found in control panel DB — migrations should run on outratib_rateb-erp only.'
                : '';
            return array_merge(['ok' => true, 'db' => $name], $stats);
        } catch (\Throwable $e) {
            return ['ok' => false, 'db' => $name, 'error' => $e->getMessage()];
        }
    }

    /** @return array<int, string> */
    public function fixErpDatabase(): array
    {
        $log = (new MigrationService())->runAll();
        try {
            $removed = (new AuthorizationService())->dedupeDuplicateRoles();
            $log[] = 'Role dedupe: removed ' . $removed . ' duplicate role row(s).';
        } catch (\Throwable $e) {
            $log[] = 'Role dedupe warning: ' . $e->getMessage();
        }

        $erp = $this->diagnoseErp();
        $log[] = 'ERP verify: db=' . ($erp['db'] ?? '?')
            . ', rateb_tables=' . ($erp['rateb_tables'] ?? '?')
            . ', permissions=' . ($erp['permissions'] ?? '?')
            . ', roles=' . ($erp['roles'] ?? '?')
            . ', duplicate_role_slugs=' . ($erp['duplicate_role_slugs'] ?? '?');

        return $log;
    }

  /** @return array<string, int|string> */
    private function stats(PDO $pdo): array
    {
        $ratebTables = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name LIKE 'rateb\\_%'"
        )->fetchColumn();

        $permissions = $this->tableCount($pdo, 'rateb_permissions');
        $roles = $this->tableCount($pdo, 'rateb_roles');
        $dupRoles = 0;
        if ($roles > 0) {
            $dupRoles = (int) $pdo->query(
                'SELECT COUNT(*) - COUNT(DISTINCT slug) FROM rateb_roles'
            )->fetchColumn();
        }

        return [
            'rateb_tables' => $ratebTables,
            'permissions' => $permissions,
            'roles' => $roles,
            'duplicate_role_slugs' => max(0, $dupRoles),
        ];
    }

    private function tableCount(PDO $pdo, string $table): int
    {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        if ($stmt === false || $stmt->rowCount() === 0) {
            return 0;
        }
        return (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn();
    }

    private function openDatabase(string $dbName): PDO
    {
        $host = defined('RATEB_DB_HOST') ? (string) RATEB_DB_HOST : '127.0.0.1';
        $port = defined('RATEB_DB_PORT') ? (int) RATEB_DB_PORT : 3306;
        $user = defined('RATEB_DB_USER') ? (string) RATEB_DB_USER : '';
        $pass = defined('RATEB_DB_PASS') ? (string) RATEB_DB_PASS : '';
        if ($user === '' && defined('DB_USER')) {
            $user = (string) DB_USER;
        }
        if ($pass === '' && defined('DB_PASS')) {
            $pass = (string) DB_PASS;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function expectedErpDbName(): string
    {
        if (function_exists('rateb_erp_database_name')) {
            return rateb_erp_database_name();
        }
        return defined('RATEB_ERP_DB_NAME') ? (string) RATEB_ERP_DB_NAME : 'outratib_rateb-erp';
    }

    private function controlPanelDbName(): string
    {
        if (defined('CONTROL_PANEL_DB_NAME')) {
            return (string) CONTROL_PANEL_DB_NAME;
        }
        if (defined('DB_NAME')) {
            return (string) DB_NAME;
        }
        return 'outratib_control_panel_db';
    }
}
