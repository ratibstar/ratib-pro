<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use PDOStatement;

/**
 * Phase B.1–C — PDO wrapper for Branch SQLite.
 *
 * - Translates MySQL dialect via SqlDialectAdapter
 * - Ignores MySQL-only PDO attributes
 * - Registers GET_LOCK / RELEASE_LOCK UDFs (SqliteAdvisoryLock)
 * - beginTransaction() uses BEGIN IMMEDIATE
 * - Phase C: captures INSERT/UPDATE/DELETE into rateb_sync_outbox
 */
final class SqliteCompatPdo extends PDO
{
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options ?? []);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [HybridSyncPdoStatement::class, []]);
        $this->registerAdvisoryLockFunctions();
        HybridSyncOutboxCapture::registerShutdownFlush();
    }

    private function registerAdvisoryLockFunctions(): void
    {
        $this->sqliteCreateFunction('GET_LOCK', static function ($name, $timeout = 0): int {
            return SqliteAdvisoryLock::get((string) $name, (int) $timeout);
        }, 2);
        $this->sqliteCreateFunction('RELEASE_LOCK', static function ($name): int {
            return SqliteAdvisoryLock::release((string) $name);
        }, 1);
    }

    public function beginTransaction(): bool
    {
        if ($this->inTransaction()) {
            return false;
        }
        $result = parent::exec('BEGIN IMMEDIATE');

        return $result !== false;
    }

    public function commit(): bool
    {
        if (!$this->inTransaction()) {
            return false;
        }
        $ok = parent::exec('COMMIT') !== false;
        if ($ok) {
            HybridSyncOutboxCapture::flushBuffered();
        }

        return $ok;
    }

    public function rollBack(): bool
    {
        HybridSyncOutboxCapture::discardBuffered();
        if (!$this->inTransaction()) {
            return false;
        }

        return parent::exec('ROLLBACK') !== false;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $translated = SqlDialectAdapter::toSqlite($query);
        $stmt = parent::prepare($translated, $options);
        if ($stmt instanceof HybridSyncPdoStatement) {
            $stmt->bindOwner($this, $query);
        }

        return $stmt;
    }

    public function exec(string $statement): int|false
    {
        $sql = SqlDialectAdapter::toSqlite($statement);
        $n = parent::exec($sql);
        if ($n !== false) {
            HybridSyncOutboxCapture::afterMutate($this, $statement, null);
        }

        return $n;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $translated = SqlDialectAdapter::toSqlite($query);
        if ($fetchMode === null && $fetchModeArgs === []) {
            $stmt = parent::query($translated);
        } else {
            $stmt = parent::query($translated, $fetchMode, ...$fetchModeArgs);
        }
        // SELECT/query does not mutate — no outbox

        return $stmt;
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        if ($attribute === PDO::MYSQL_ATTR_INIT_COMMAND
            || (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS') && $attribute === PDO::MYSQL_ATTR_MULTI_STATEMENTS)
            || (defined('PDO::MYSQL_ATTR_LOCAL_INFILE') && $attribute === PDO::MYSQL_ATTR_LOCAL_INFILE)
            || (defined('PDO::MYSQL_ATTR_FOUND_ROWS') && $attribute === PDO::MYSQL_ATTR_FOUND_ROWS)
            || (defined('PDO::MYSQL_ATTR_IGNORE_SPACE') && $attribute === PDO::MYSQL_ATTR_IGNORE_SPACE)
            || (defined('PDO::MYSQL_ATTR_COMPRESS') && $attribute === PDO::MYSQL_ATTR_COMPRESS)
            || (defined('PDO::MYSQL_ATTR_SSL_CA') && $attribute === PDO::MYSQL_ATTR_SSL_CA)
            || (defined('PDO::MYSQL_ATTR_SSL_KEY') && $attribute === PDO::MYSQL_ATTR_SSL_KEY)
            || (defined('PDO::MYSQL_ATTR_SSL_CERT') && $attribute === PDO::MYSQL_ATTR_SSL_CERT)
        ) {
            return true;
        }

        if ($attribute >= 1000) {
            return true;
        }

        return parent::setAttribute($attribute, $value);
    }
}
