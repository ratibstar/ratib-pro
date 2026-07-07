<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

use PDO;
use Rateb\PlatformCatalog\Core\Database;

final class MigrationRunner
{
    /** @var list<class-string<MigrationInterface>> */
    private array $phpMigrationClasses;

    /**
     * @param list<class-string<MigrationInterface>>|null $phpMigrationClasses
     */
    public function __construct(?array $phpMigrationClasses = null)
    {
        $this->phpMigrationClasses = $phpMigrationClasses ?? $this->discoverPhpMigrations();
    }

    /** @return list<string> */
    public function runAll(): array
    {
        $log = [];
        $pdo = Database::writeConnection();
        $dbName = $this->liveDatabaseName($pdo);
        $log[] = 'Connected to database: ' . $dbName;
        $this->assertCatalogTargetDatabase($dbName);
        $this->ensureMigrationsTable($pdo);

        $entries = $this->collectMigrationEntries();
        $ran = 0;

        foreach ($entries as $entry) {
            $key = self::normalizeMigrationKey($entry['key']);
            if ($this->isApplied($pdo, $key)) {
                $log[] = 'Already applied: ' . $key;
                continue;
            }

            $log[] = 'Running ' . $key . ' (' . $entry['type'] . ')…';

            if ($entry['type'] === 'sql') {
                $sql = file_get_contents($entry['path']);
                if ($sql === false || trim($sql) === '') {
                    continue;
                }
                $this->execSqlFile($pdo, AbstractMigration::normalizeSql($sql));
            } else {
                $class = $entry['class'];
                $migration = new $class($pdo);
                if (self::normalizeMigrationKey($migration->name()) !== $key) {
                    throw new \RuntimeException('Migration class name mismatch: ' . $class);
                }
                $migration->up();
            }

            $this->markApplied($pdo, $key);
            $log[] = 'Done: ' . $key;
            $ran++;
        }

        if ($ran === 0) {
            $log[] = 'No new migrations to run.';
        }

        return $log;
    }

    /** @return list<string> */
    public function rollbackLast(): array
    {
        $log = [];
        $pdo = Database::writeConnection();
        $this->ensureMigrationsTable($pdo);

        $row = Database::fetchOne(
            'SELECT filename FROM catalog_migrations ORDER BY id DESC LIMIT 1',
            [],
            false
        );

        if ($row === null) {
            $log[] = 'No migrations to roll back.';

            return $log;
        }

        $key = self::normalizeMigrationKey((string) $row['filename']);
        $log[] = 'Rolling back ' . $key . '…';

        $class = $this->findPhpMigrationClass($key);
        if ($class !== null) {
            $migration = new $class($pdo);
            $migration->down();
        } else {
            $downFile = $this->findLegacyDownFile($key);
            if ($downFile !== null) {
                $sql = file_get_contents($downFile);
                if ($sql !== false && trim($sql) !== '') {
                    $this->execSqlFile($pdo, AbstractMigration::normalizeSql($sql));
                }
            } else {
                throw new \RuntimeException('No down() migration available for: ' . $key);
            }
        }

        $stmt = $pdo->prepare('DELETE FROM catalog_migrations WHERE filename = :filename OR filename = :key');
        $stmt->execute([
            'filename' => (string) $row['filename'],
            'key' => $key,
        ]);

        $log[] = 'Rolled back: ' . $key;

        return $log;
    }

    /**
     * @return list<array{key:string,type:string,path?:string,class?:class-string<MigrationInterface>}>
     */
    private function collectMigrationEntries(): array
    {
        $root = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : dirname(__DIR__, 4);
        $entries = [];

        foreach (glob($root . '/migrations/*.sql') ?: [] as $file) {
            $name = basename($file);
            if (!$this->isRunnableSqlMigration($name)) {
                continue;
            }
            $key = self::normalizeMigrationKey($name);
            $entries[$key] = ['key' => $key, 'type' => 'sql', 'path' => $file];
        }

        foreach ($this->phpMigrationClasses as $class) {
            if (!is_subclass_of($class, MigrationInterface::class)) {
                continue;
            }
            $instance = new $class(Database::writeConnection());
            $key = self::normalizeMigrationKey($instance->name());
            if (!isset($entries[$key])) {
                $entries[$key] = ['key' => $key, 'type' => 'php', 'class' => $class];
            }
        }

        ksort($entries, SORT_STRING);

        return array_values($entries);
    }

    /** @return list<class-string<MigrationInterface>> */
    private function discoverPhpMigrations(): array
    {
        $root = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : dirname(__DIR__, 4);
        $classes = [];

        foreach (glob($root . '/app/Infrastructure/Persistence/Migrations/M*.php') ?: [] as $file) {
            $base = basename($file, '.php');
            $class = 'Rateb\\PlatformCatalog\\Infrastructure\\Persistence\\Migrations\\' . $base;
            if (class_exists($class) && is_subclass_of($class, MigrationInterface::class)) {
                $classes[] = $class;
            }
        }

        usort($classes, static fn (string $a, string $b): int => strcmp($a, $b));

        return $classes;
    }

  /**
     * @param class-string<MigrationInterface> $class
     */
    private function findPhpMigrationClass(string $key): ?string
    {
        foreach ($this->phpMigrationClasses as $class) {
            $instance = new $class(Database::writeConnection());
            if (self::normalizeMigrationKey($instance->name()) === $key) {
                return $class;
            }
        }

        return null;
    }

    private function findLegacyDownFile(string $key): ?string
    {
        $root = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : dirname(__DIR__, 4);
        $candidate = $root . '/migrations/' . $key . '.down.sql';
        if (is_file($candidate)) {
            return $candidate;
        }

        return null;
    }

    private function assertCatalogTargetDatabase(string $dbName): void
    {
        $expected = defined('RATEB_PLATFORM_CATALOG_DB_NAME')
            ? (string) RATEB_PLATFORM_CATALOG_DB_NAME
            : 'admin_rateb_platform_catalog';

        if ($dbName !== '' && stripos($dbName, 'platform_catalog') === false && $dbName !== $expected) {
            throw new \RuntimeException(
                'Refusing to run catalog migrations on non-catalog database: ' . $dbName
            );
        }
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS catalog_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_catalog_migrations_filename (filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function isRunnableSqlMigration(string $name): bool
    {
        if (!preg_match('/^\d{3}_[A-Za-z0-9_\-]+\.sql$/', $name)) {
            return false;
        }

        if (str_starts_with($name, '000_')) {
            return false;
        }

        return !str_contains(strtolower($name), 'diagnose');
    }

    private function isApplied(PDO $pdo, string $key): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM catalog_migrations
             WHERE filename = :key
                OR filename = :sql
                OR REPLACE(filename, ".sql", "") = :canonical
             LIMIT 1'
        );
        $stmt->execute([
            'key' => $key,
            'sql' => $key . '.sql',
            'canonical' => $key,
        ]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();

        return $row !== false;
    }

    private function markApplied(PDO $pdo, string $key): void
    {
        $stmt = $pdo->prepare('INSERT INTO catalog_migrations (filename) VALUES (:filename)');
        $stmt->execute(['filename' => self::normalizeMigrationKey($key)]);
    }

    private function liveDatabaseName(PDO $pdo): string
    {
        $row = $pdo->query('SELECT DATABASE()')->fetch(PDO::FETCH_NUM);

        return is_array($row) ? (string) ($row[0] ?? '') : '';
    }

    private function execSqlFile(PDO $pdo, string $sql): void
    {
        $statements = preg_split('/;\s*\n/', $sql) ?: [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
                $code = (int) $e->getCode();
                $msg = $e->getMessage();
                $benign = in_array($code, [1050, 1060, 1061, 1062, 1091], true)
                    || str_contains($msg, 'Duplicate')
                    || str_contains($msg, 'already exists');
                if (!$benign) {
                    throw $e;
                }
            }
        }
    }

    public static function normalizeMigrationKey(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/\.(sql|php)$/i', '', $name) ?? $name;

        return $name;
    }
}
