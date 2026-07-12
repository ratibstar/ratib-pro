<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase B.1 — MySQL → SQLite SQL translator (Core only).
 *
 * Applied only when Branch SQLite is active. MySQL path never calls this.
 * Controllers/Services/Models keep issuing MySQL dialect SQL transparently.
 */
final class SqlDialectAdapter
{
    /**
     * Translate a MySQL SQL statement to SQLite-compatible SQL.
     * Idempotent for already-SQLite SQL (PRAGMA/CREATE/etc.).
     */
    public static function toSqlite(string $sql): string
    {
        $sql = trim($sql);
        if ($sql === '') {
            return $sql;
        }

        // Pass-through pure SQLite / DDL bootstrap
        if (preg_match('/^\s*(PRAGMA|CREATE\s+TABLE|CREATE\s+(UNIQUE\s+)?INDEX|DROP\s+|ATTACH\s+|VACUUM)\b/i', $sql)) {
            return $sql;
        }

        $sql = self::rewriteShowTables($sql);
        $sql = self::rewriteShowColumns($sql);
        $sql = self::rewriteInformationSchema($sql);
        $sql = self::rewriteInsertIgnore($sql);
        $sql = self::rewriteOnDuplicateKey($sql);
        $sql = self::rewriteDeleteJoin($sql);
        $sql = self::rewriteDateFormat($sql);
        $sql = self::rewriteDateAddSub($sql);
        $sql = self::rewriteScalarFunctions($sql);
        $sql = self::rewriteIfFunction($sql);
        $sql = self::rewriteBinaryKeyword($sql);
        $sql = self::rewriteBackticks($sql);

        return $sql;
    }

    private static function rewriteShowTables(string $sql): string
    {
        if (!preg_match('/^\s*SHOW\s+TABLES\b/i', $sql)) {
            return $sql;
        }
        $like = '';
        if (preg_match('/\bLIKE\s+((?:\'(?:\\\\\'|[^\'])*\')|(?:"(?:\\\\"|[^"])*"))/i', $sql, $m)) {
            $like = ' AND name LIKE ' . $m[1];
        }

        return "SELECT name AS `Tables_in_branch` FROM sqlite_master"
            . " WHERE type = 'table' AND name NOT LIKE 'sqlite_%'" . $like;
    }

    private static function rewriteShowColumns(string $sql): string
    {
        if (!preg_match('/^\s*SHOW\s+(FULL\s+)?COLUMNS\s+FROM\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $sql, $m)) {
            return $sql;
        }
        $table = $m[2];
        $like = '';
        if (preg_match('/\bLIKE\s+((?:\'(?:\\\\\'|[^\'])*\')|(?:"(?:\\\\"|[^"])*")|:[\w]+)/i', $sql, $lm)) {
            $like = ' WHERE name LIKE ' . $lm[1];
        }

        return "SELECT name AS Field, type AS Type,"
            . " CASE WHEN \"notnull\" = 1 THEN 'NO' ELSE 'YES' END AS \"Null\","
            . " CASE WHEN pk > 0 THEN 'PRI' ELSE '' END AS \"Key\","
            . " dflt_value AS \"Default\", '' AS Extra"
            . " FROM pragma_table_info(" . self::quoteIdent($table) . ")" . $like;
    }

    private static function rewriteInformationSchema(string $sql): string
    {
        if (!str_contains(strtoupper($sql), 'INFORMATION_SCHEMA')) {
            return $sql;
        }

        // COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE … TABLE_NAME = :t / 'x'
        if (preg_match(
            '/SELECT\s+COUNT\s*\(\s*\*\s*\)\s+FROM\s+INFORMATION_SCHEMA\.TABLES\b/i',
            $sql
        )) {
            if (preg_match('/TABLE_NAME\s*=\s*(:[a-zA-Z_][\w]*|\'[^\']+\'|\"[^\"]+\")/i', $sql, $tm)) {
                return 'SELECT COUNT(*) FROM sqlite_master WHERE type = \'table\' AND name = ' . $tm[1];
            }

            return "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'";
        }

        // COLUMNS existence checks
        if (preg_match(
            '/SELECT\s+COUNT\s*\(\s*\*\s*\)\s+FROM\s+INFORMATION_SCHEMA\.COLUMNS\b/i',
            $sql
        )) {
            $table = null;
            $column = null;
            if (preg_match('/TABLE_NAME\s*=\s*(:[a-zA-Z_][\w]*|\'[^\']+\'|\"[^\"]+\")/i', $sql, $tm)) {
                $table = $tm[1];
            }
            if (preg_match('/COLUMN_NAME\s*=\s*(:[a-zA-Z_][\w]*|\'[^\']+\'|\"[^\"]+\")/i', $sql, $cm)) {
                $column = $cm[1];
            }
            if ($table !== null && $column !== null && $table[0] !== ':' && $column[0] !== ':') {
                $t = trim($table, "'\"");
                $c = trim($column, "'\"");

                return "SELECT COUNT(*) FROM pragma_table_info(" . self::quoteIdent($t) . ") WHERE name = " . self::quoteString($c);
            }
            if ($table !== null && $column !== null) {
                // Bound params — use sqlite_master fallback is insufficient; approximate via rewrite to always check via PHP-side is hard.
                // Use: SELECT COUNT(*) FROM pragma_table_info('x') — can't bind table name in pragma easily.
                // Leave a workable form when literals; for binds, use subquery over sqlite_master only for tables.
                return $sql; // fall through — rare with binds for column checks
            }
        }

        // Generic DATABASE() in INFORMATION_SCHEMA predicates → remove schema filter
        $sql = preg_replace('/\bTABLE_SCHEMA\s*=\s*DATABASE\s*\(\s*\)/i', '1=1', $sql) ?? $sql;
        $sql = preg_replace('/\bAND\s+1=1\b/i', '', $sql) ?? $sql;

        return $sql;
    }

    private static function rewriteInsertIgnore(string $sql): string
    {
        return preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT OR IGNORE INTO', $sql) ?? $sql;
    }

    private static function rewriteOnDuplicateKey(string $sql): string
    {
        if (!preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i', $sql)) {
            return $sql;
        }

        if (!preg_match(
            '/^(.*)\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.+)$/is',
            $sql,
            $m
        )) {
            return $sql;
        }
        $insert = trim($m[1]);
        $updates = trim($m[2]);
        $updates = preg_replace('/\bVALUES\s*\(\s*([a-zA-Z0-9_]+)\s*\)/i', 'excluded.$1', $updates) ?? $updates;

        $conflict = 'id';
        if (preg_match('/INSERT\s+(?:OR\s+IGNORE\s+)?INTO\s+[`"]?([a-zA-Z0-9_]+)[`"]?\s*\(([^)]+)\)/i', $insert, $im)) {
            $cols = array_map(static fn ($c) => strtolower(trim($c, " \t\n\r\0\x0B`\"")), explode(',', $im[2]));
            if (in_array('id', $cols, true)) {
                $conflict = 'id';
            } elseif ($cols !== []) {
                $conflict = $cols[0];
            }
        }

        return $insert . ' ON CONFLICT(' . $conflict . ') DO UPDATE SET ' . $updates;
    }

    private static function rewriteDeleteJoin(string $sql): string
    {
        if (!preg_match(
            '/^\s*DELETE\s+([a-zA-Z_][\w]*)\s+FROM\s+([`"]?[a-zA-Z0-9_]+[`"]?)\s+\1\s+/is',
            $sql,
            $m
        )) {
            return $sql;
        }
        $alias = $m[1];
        $table = trim($m[2], '`"');
        $rest = substr($sql, strlen($m[0]));

        return 'DELETE FROM ' . $table
            . ' WHERE rowid IN (SELECT ' . $alias . '.rowid FROM ' . $table . ' AS ' . $alias . ' ' . $rest . ')';
    }

    private static function rewriteDateFormat(string $sql): string
    {
        $guard = 0;
        while ($guard++ < 50 && preg_match('/DATE_FORMAT\s*\(/i', $sql)) {
            $start = stripos($sql, 'DATE_FORMAT');
            if ($start === false) {
                break;
            }
            $open = strpos($sql, '(', $start);
            if ($open === false) {
                break;
            }
            $args = self::splitArgs(self::extractParenContents($sql, $open));
            if (count($args) < 2) {
                break;
            }
            $expr = trim($args[0]);
            $fmtRaw = trim($args[1]);
            $fmt = trim($fmtRaw, " \t\n\r'\"");
            $sqliteFmt = self::mysqlFormatToSqlite($fmt);
            $end = self::matchingParenEnd($sql, $open);
            $replacement = 'strftime(' . self::quoteString($sqliteFmt) . ', ' . $expr . ')';
            $sql = substr($sql, 0, $start) . $replacement . substr($sql, $end + 1);
        }

        return $sql;
    }

    private static function rewriteDateAddSub(string $sql): string
    {
        $sql = self::rewriteDateIntervalFn($sql, 'DATE_ADD', '+');
        $sql = self::rewriteDateIntervalFn($sql, 'DATE_SUB', '-');

        return $sql;
    }

    private static function rewriteDateIntervalFn(string $sql, string $fn, string $sign): string
    {
        $guard = 0;
        while ($guard++ < 50) {
            if (!preg_match('/\b' . preg_quote($fn, '/') . '\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }
            $start = (int) $m[0][1];
            $open = strpos($sql, '(', $start);
            if ($open === false) {
                break;
            }
            $inner = self::extractParenContents($sql, $open);
            if (!preg_match('/^(.*),\s*INTERVAL\s+(\d+)\s+(DAY|HOUR|MINUTE|SECOND|MONTH|YEAR)S?\s*$/is', $inner, $im)) {
                break;
            }
            $expr = trim($im[1]);
            $n = (int) $im[2];
            $unit = strtolower($im[3]);
            $map = [
                'day' => 'days',
                'hour' => 'hours',
                'minute' => 'minutes',
                'second' => 'seconds',
                'month' => 'months',
                'year' => 'years',
            ];
            $mod = $sign . $n . ' ' . ($map[$unit] ?? ($unit . 's'));
            $end = self::matchingParenEnd($sql, $open);
            $replacement = 'datetime(' . $expr . ', ' . self::quoteString($mod) . ')';
            $sql = substr($sql, 0, $start) . $replacement . substr($sql, $end + 1);
        }

        return $sql;
    }

    private static function rewriteScalarFunctions(string $sql): string
    {
        $sql = preg_replace('/\bDATABASE\s*\(\s*\)/i', self::quoteString('branch_sqlite'), $sql) ?? $sql;
        $sql = preg_replace('/\bCURDATE\s*\(\s*\)/i', "date('now')", $sql) ?? $sql;
        $sql = preg_replace('/\bCURRENT_DATE\b/i', "date('now')", $sql) ?? $sql;
        $sql = preg_replace('/\bNOW\s*\(\s*\)/i', "datetime('now')", $sql) ?? $sql;
        $sql = preg_replace('/\bUTC_TIMESTAMP\s*\(\s*\)/i', "datetime('now')", $sql) ?? $sql;
        $sql = preg_replace('/\bUNIX_TIMESTAMP\s*\(\s*\)/i', "strftime('%s','now')", $sql) ?? $sql;
        $sql = preg_replace('/\bUNIX_TIMESTAMP\s*\(/i', 'strftime(\'%s\',', $sql) ?? $sql;
        $sql = preg_replace('/\bFROM_UNIXTIME\s*\(/i', 'datetime(', $sql) ?? $sql;

        return $sql;
    }

    private static function rewriteIfFunction(string $sql): string
    {
        $guard = 0;
        while ($guard++ < 40 && preg_match('/(?<![A-Za-z_])IF\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
            $start = (int) $m[0][1];
            $open = strpos($sql, '(', $start);
            if ($open === false) {
                break;
            }
            $inner = self::extractParenContents($sql, $open);
            $args = self::splitArgs($inner);
            if (count($args) < 3) {
                break;
            }
            $end = self::matchingParenEnd($sql, $open);
            $replacement = 'CASE WHEN (' . trim($args[0]) . ') THEN (' . trim($args[1]) . ') ELSE (' . trim($args[2]) . ') END';
            $sql = substr($sql, 0, $start) . $replacement . substr($sql, $end + 1);
        }

        return $sql;
    }

    private static function rewriteBinaryKeyword(string $sql): string
    {
        return preg_replace('/\bBINARY\s+/i', '', $sql) ?? $sql;
    }

    private static function rewriteBackticks(string $sql): string
    {
        // SQLite accepts backticks in recent builds; normalize to double-quotes for safety
        return preg_replace_callback('/`([a-zA-Z_][a-zA-Z0-9_]*)`/', static fn ($m) => '"' . $m[1] . '"', $sql) ?? $sql;
    }

    private static function mysqlFormatToSqlite(string $fmt): string
    {
        // Convert MySQL date format tokens to strftime
        $out = '';
        $len = strlen($fmt);
        for ($i = 0; $i < $len; $i++) {
            if ($fmt[$i] === '%' && $i + 1 < $len) {
                $t = $fmt[$i + 1];
                $out .= match ($t) {
                    'i' => '%M', // minutes
                    's' => '%S',
                    'Y', 'y', 'm', 'd', 'H', 'h', 'M', 'S', 'w', 'W', 'j', 'U' => '%' . $t,
                    default => '%' . $t,
                };
                $i++;
                continue;
            }
            $out .= $fmt[$i];
        }

        return $out;
    }

    private static function extractParenContents(string $sql, int $openParenPos): string
    {
        $end = self::matchingParenEnd($sql, $openParenPos);

        return substr($sql, $openParenPos + 1, $end - $openParenPos - 1);
    }

    private static function matchingParenEnd(string $sql, int $openParenPos): int
    {
        $depth = 0;
        $len = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        for ($i = $openParenPos; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                continue;
            }
            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                continue;
            }
            if ($inSingle || $inDouble) {
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $len - 1;
    }

    /** @return list<string> */
    private static function splitArgs(string $inner): array
    {
        $args = [];
        $buf = '';
        $depth = 0;
        $inSingle = false;
        $inDouble = false;
        $len = strlen($inner);
        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];
            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                $buf .= $ch;
                continue;
            }
            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                $buf .= $ch;
                continue;
            }
            if (!$inSingle && !$inDouble) {
                if ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth--;
                } elseif ($ch === ',' && $depth === 0) {
                    $args[] = $buf;
                    $buf = '';
                    continue;
                }
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $args[] = $buf;
        }

        return $args;
    }

    private static function quoteIdent(string $name): string
    {
        return "'" . str_replace("'", "''", $name) . "'";
    }

    private static function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
