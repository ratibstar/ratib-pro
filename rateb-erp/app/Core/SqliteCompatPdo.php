<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use PDOStatement;

/**
 * Phase B.1–B.2.1 — PDO wrapper for Branch SQLite.
 *
 * - Translates MySQL dialect via SqlDialectAdapter
 * - Ignores MySQL-only PDO attributes
 * - Registers GET_LOCK / RELEASE_LOCK UDFs (SqliteAdvisoryLock)
 * - beginTransaction() uses BEGIN IMMEDIATE so writers take a RESERVED lock early
 *   (enterprise substitute for MySQL row locks under FOR UPDATE inside a transaction)
 */
final class SqliteCompatPdo extends PDO
{
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options ?? []);
        $this->registerAdvisoryLockFunctions();
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

    /**
     * Prefer BEGIN IMMEDIATE over deferred BEGIN.
     * Acquires a RESERVED lock up front so concurrent writers serialize before
     * mutating rows — closer to MySQL FOR UPDATE write-intent under a transaction.
     */
    public function beginTransaction(): bool
    {
        if ($this->inTransaction()) {
            return false;
        }
        // Avoid parent::beginTransaction() (DEFERRED). Use IMMEDIATE for enterprise safety.
        $result = parent::exec('BEGIN IMMEDIATE');

        return $result !== false;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(SqlDialectAdapter::toSqlite($query), $options);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(SqlDialectAdapter::toSqlite($statement));
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = SqlDialectAdapter::toSqlite($query);
        if ($fetchMode === null && $fetchModeArgs === []) {
            return parent::query($query);
        }

        return parent::query($query, $fetchMode, ...$fetchModeArgs);
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
