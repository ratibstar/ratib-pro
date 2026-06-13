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
        [$pdo, $dbName] = $this->migrationConnection();
        $log[] = 'Connected to database: ' . $dbName;
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
        $applied = (bool) $stmt->fetch();
        $this->drainStatement($stmt);
        return $applied;
    }

    private function markApplied(PDO $pdo, string $filename): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO rateb_migrations (filename) VALUES (:f)');
        $stmt->execute(['f' => $filename]);
    }

    /** Existing DBs installed before rateb_migrations tracking — skip re-running 001–012. */
    private function seedLegacyAppliedMigrations(PDO $pdo, array &$log): void
    {
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
            if ($this->isApplied($pdo, $name)) {
                continue;
            }
            $this->markApplied($pdo, $name);
            $marked++;
        }
        if ($marked > 0) {
            $log[] = 'Legacy schema: marked migrations 001–012 as already applied (' . $marked . ' new rows).';
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
        $this->drainStatement($stmt);
    }

    private function drainStatement(\PDOStatement $stmt): void
    {
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

    /** @return array{0:PDO,1:string} */
    private function migrationConnection(): array
    {
        Database::disconnect();
        $candidates = function_exists('rateb_erp_database_candidates')
            ? rateb_erp_database_candidates()
            : [defined('RATEB_DB_NAME') ? (string) RATEB_DB_NAME : 'outratib_rateb-erp'];
        $last = null;
        foreach ($candidates as $dbName) {
            try {
                return [$this->openMigrationPdo($dbName), $dbName];
            } catch (\PDOException $e) {
                $last = $e;
            }
        }
        if ($last instanceof \PDOException) {
            throw $last;
        }
        throw new \PDOException('RATEB ERP migration database connection failed.');
    }

    private function openMigrationPdo(string $dbName): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            RATEB_DB_HOST,
            RATEB_DB_PORT,
            $dbName
        );
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[\PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
        }
        return new \PDO($dsn, RATEB_DB_USER, RATEB_DB_PASS, $options);
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

    /** @return array<int, string> */
    public function rollbackLast(): array
    {
        $log = [];
        [$pdo, $dbName] = $this->migrationConnection();
        $log[] = 'Connected to database: ' . $dbName;
        $this->ensureMigrationsTable($pdo);

        $stmt = $pdo->query('SELECT filename FROM rateb_migrations ORDER BY id DESC LIMIT 1');
        $row = $stmt !== false ? $stmt->fetch() : false;
        if (!$row || empty($row['filename'])) {
            $log[] = 'No migration to rollback.';
            return $log;
        }
        $filename = (string) $row['filename'];
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $downFile = $root . '/migrations/' . preg_replace('/\.sql$/', '.down.sql', $filename);
        if (!is_file($downFile)) {
            $log[] = 'Rollback file missing: ' . basename($downFile);
            return $log;
        }
        $sql = file_get_contents($downFile);
        if ($sql === false || trim($sql) === '') {
            $log[] = 'Rollback file empty.';
            return $log;
        }
        $log[] = 'Rolling back ' . $filename . '…';
        $this->execSqlFile($pdo, $sql);
        $pdo->prepare('DELETE FROM rateb_migrations WHERE filename = :f')->execute(['f' => $filename]);
        $log[] = 'Rolled back: ' . $filename;
        return $log;
    }
}
