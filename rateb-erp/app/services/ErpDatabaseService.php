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
        Database::disconnect();
        $log = (new MigrationService())->runAll();
        try {
            if ($this->cmsArabicNeedsRepair()) {
                $repair = (new CmsArabicRepairService())->repair();
                $log[] = 'CMS Arabic repair: ' . $repair['updated'] . ' row(s); hero=' . $repair['hero_title'];
            } else {
                $log[] = 'CMS Arabic repair: skipped (content OK).';
            }
        } catch (\Throwable $e) {
            $log[] = 'CMS Arabic repair warning: ' . $e->getMessage();
        }
        Database::disconnect();
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

    private function cmsArabicNeedsRepair(): bool
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query(
                "SELECT title_ar FROM rateb_cms_sections WHERE page_slug='home' AND section_key='hero' LIMIT 1"
            );
            if ($stmt === false) {
                return false;
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if (!is_array($row)) {
                return false;
            }
            $title = (string) ($row['title_ar'] ?? '');
            return $title === '' || strpos($title, '?') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

  /** @return array<string, int|string> */
    private function stats(PDO $pdo): array
    {
        $ratebTables = $this->scalar($pdo,
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name LIKE 'rateb\\_%'"
        );

        $permissions = $this->tableCount($pdo, 'rateb_permissions');
        $roles = $this->tableCount($pdo, 'rateb_roles');
        $dupRoles = 0;
        if ($roles > 0) {
            $dupRoles = $this->scalar($pdo, 'SELECT COUNT(*) - COUNT(DISTINCT slug) FROM rateb_roles');
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
        if ($stmt === false) {
            return 0;
        }
        $exists = $stmt->fetch() !== false;
        $stmt->closeCursor();
        if (!$exists) {
            return 0;
        }
        return $this->scalar($pdo, 'SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`');
    }

    private function scalar(PDO $pdo, string $sql): int
    {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return 0;
        }
        $val = $stmt->fetchColumn();
        $stmt->closeCursor();
        return (int) $val;
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
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        return new PDO($dsn, $user, $pass, $options);
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
