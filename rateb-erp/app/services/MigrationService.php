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
        $this->ensureMigrationsTable($pdo);
        $this->seedLegacyAppliedMigrations($pdo, $log);

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
            if ($this->isApplied($pdo, $name)) {
                $log[] = 'Already applied: ' . $name;
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
            $this->markApplied($pdo, $name);
            $log[] = 'Done: ' . $name;
            $ran++;
        }

        if ($ran === 0) {
            $log[] = 'No new migrations to apply.';
        } else {
            $log[] = 'Applied ' . $ran . ' migration file(s).';
        }

        return $log;
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_migration_filename (filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function isApplied(PDO $pdo, string $filename): bool
    {
        $stmt = $pdo->prepare('SELECT id FROM rateb_migrations WHERE filename = :f LIMIT 1');
        $stmt->execute(['f' => $filename]);
        return (bool) $stmt->fetch();
    }

    private function markApplied(PDO $pdo, string $filename): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO rateb_migrations (filename) VALUES (:f)');
        $stmt->execute(['f' => $filename]);
    }

    /** Existing DBs installed before rateb_migrations tracking — skip re-running 001–012. */
    private function seedLegacyAppliedMigrations(PDO $pdo, array &$log): void
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM rateb_migrations');
        $count = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
        if ($stmt instanceof \PDOStatement) {
            $stmt->closeCursor();
        }
        if ($count > 0) {
            return;
        }

        $check = $pdo->query("SHOW TABLES LIKE 'rateb_companies'");
        $hasSchema = $check !== false && $check->fetch() !== false;
        if ($check instanceof \PDOStatement) {
            $check->closeCursor();
        }
        if (!$hasSchema) {
            return;
        }

        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $files = glob($root . '/migrations/*.sql') ?: [];
        sort($files);
        $marked = 0;
        foreach ($files as $file) {
            $name = basename($file);
            if (!$this->isRunnableMigration($name)) {
                continue;
            }
            if (!preg_match('/^(\d{3})_/', $name, $m)) {
                continue;
            }
            if ((int) $m[1] >= 13) {
                continue;
            }
            $this->markApplied($pdo, $name);
            $marked++;
        }
        if ($marked > 0) {
            $log[] = 'Legacy schema detected: marked migrations 001–012 as already applied (' . $marked . ' files).';
        }
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
        foreach ($this->splitStatements($sql) as $statement) {
            if ($statement === '') {
                continue;
            }
            try {
                $this->execStatement($pdo, $statement);
            } catch (\PDOException $e) {
                if ($this->isBenignMigrationError($e->getMessage())) {
                    continue;
                }
                throw $e;
            }
        }
    }

    private function execStatement(PDO $pdo, string $statement): void
    {
        $stmt = $pdo->query($statement);
        if ($stmt === false) {
            return;
        }
        do {
            $stmt->fetchAll();
        } while ($stmt->nextRowset());
        $stmt->closeCursor();
    }

    /** @return array<int, string> */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0) {
                continue;
            }
            $buffer .= $line . "\n";
            if (preg_match('/;\s*$/', $trimmed)) {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
            }
        }
        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }
        return $statements;
    }

    private function isBenignMigrationError(string $message): bool
    {
        foreach (['1050', '1060', '1061', '1091', '1826', '1825', '1062'] as $code) {
            if (strpos($message, $code) !== false) {
                return true;
            }
        }
        return false;
    }

    public function isSchemaReady(): bool
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'rateb_companies'");
            $row = $stmt !== false ? $stmt->fetch() : false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
            return $row !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
