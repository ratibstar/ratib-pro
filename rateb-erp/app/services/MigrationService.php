<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use PDO;

final class MigrationService
{
    /** @return array<int, string> */
    public function runAll(): array
    {
        $log = [];
        $pdo = Database::connection();
        $log[] = 'Connected to database: ' . Database::resolvedDatabaseName();

        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $files = glob($root . '/migrations/*.sql') ?: [];
        sort($files);

        $ran = 0;
        foreach ($files as $file) {
            $name = basename($file);
            if (!$this->isRunnableMigration($name)) {
                $log[] = 'Skipped ' . $name . ' (manual/full install only)';
                continue;
            }
            if (!is_file($file)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                continue;
            }
            $log[] = 'Running ' . $name . '…';
            $this->execSqlFile($pdo, $sql);
            $log[] = 'Done: ' . $name;
            $ran++;
        }

        if ($ran === 0) {
            $log[] = 'No incremental migration files found (001–999).';
        } else {
            $log[] = 'All migrations completed (' . $ran . ' file(s)).';
        }

        return $log;
    }

    private function isRunnableMigration(string $basename): bool
    {
        if (!preg_match('/^\d{3}_[A-Za-z0-9_\-]+\.sql$/', $basename)) {
            return false;
        }
        if (strncmp($basename, '000_', 4) === 0) {
            return false;
        }
        return true;
    }

    private function execSqlFile(PDO $pdo, string $sql): void
    {
        $sql = preg_replace('/^\s*USE\s+`[^`]+`\s*;\s*/mi', '', $sql) ?? $sql;
        $pdo->exec($sql);
    }

    public function isSchemaReady(): bool
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'rateb_companies'");
            return $stmt !== false && $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
