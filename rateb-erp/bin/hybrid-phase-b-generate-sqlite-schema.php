<?php
declare(strict_types=1);

/**
 * Phase B — generate SQLite DDL from a live MySQL ERP schema dump.
 *
 * Usage:
 *   php bin/hybrid-phase-b-generate-sqlite-schema.php
 *   php bin/hybrid-phase-b-generate-sqlite-schema.php --mysql-dump=C:/path/dump.sql
 *
 * Does not modify Controllers/Services/Models. Writes:
 *   schema/sqlite/branch-erp-schema.sql
 */

$root = dirname(__DIR__);
$outDir = $root . '/schema/sqlite';
$outFile = $outDir . '/branch-erp-schema.sql';

$dumpPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--mysql-dump=')) {
        $dumpPath = substr($arg, 13);
    }
}

if ($dumpPath === null) {
    $dumpPath = sys_get_temp_dir() . '/rateb-mysql-schema.sql';
}

if (!is_readable($dumpPath)) {
    fwrite(STDERR, "MySQL dump not found: {$dumpPath}\n");
    fwrite(STDERR, "Create with: mysqldump -uroot --no-data --skip-comments --compact DB > dump.sql\n");
    exit(1);
}

$raw = (string) file_get_contents($dumpPath);
echo "Input bytes: " . strlen($raw) . PHP_EOL;

/**
 * Convert one MySQL CREATE TABLE statement to SQLite.
 */
function rateb_mysql_create_to_sqlite(string $stmt): ?array
{
    $stmt = trim($stmt);
    if (!preg_match('/^CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s*\(/i', $stmt, $m)) {
        return null;
    }
    $table = $m[1];

    // Extract body between first ( and matching last ) before ENGINE/CHARSET
    if (!preg_match('/^CREATE\s+TABLE\s+`?' . preg_quote($table, '/') . '`?\s*\((.*)\)\s*(?:ENGINE|;|$)/is', $stmt, $bm)) {
        // fallback: strip trailing engine
        $body = $stmt;
        $body = preg_replace('/^CREATE\s+TABLE\s+`?' . preg_quote($table, '/') . '`?\s*\(/i', '', $body) ?? '';
        $body = preg_replace('/\)\s*ENGINE=.*$/is', '', $body) ?? $body;
    } else {
        $body = $bm[1];
    }

    $lines = preg_split('/\r?\n/', $body) ?: [];
    $cols = [];
    $indexes = [];
    $primaryCols = [];

    foreach ($lines as $line) {
        $line = trim($line);
        $line = rtrim($line, ',');
        if ($line === '') {
            continue;
        }

        // Skip FK constraints — SQLite order/enforcement fragile across 475 tables
        if (preg_match('/^(CONSTRAINT\s+\S+\s+)?FOREIGN\s+KEY/i', $line)) {
            continue;
        }

        if (preg_match('/^PRIMARY\s+KEY\s*\((.+)\)/i', $line, $pm)) {
            $primaryCols = array_map(static function ($c) {
                return trim($c, " `\t");
            }, explode(',', $pm[1]));
            continue;
        }

        if (preg_match('/^(UNIQUE\s+)?(?:KEY|INDEX)\s+`?([a-zA-Z0-9_]+)`?\s*\((.+)\)/i', $line, $im)) {
            $unique = strtoupper(trim((string) ($im[1] ?? ''))) === 'UNIQUE';
            $idxName = $im[2];
            $idxCols = $im[3];
            $idxCols = preg_replace('/`/', '', $idxCols) ?? $idxCols;
            // Drop prefix lengths varchar(191)
            $idxCols = preg_replace('/\(\d+\)/', '', $idxCols) ?? $idxCols;
            $indexes[] = [
                'unique' => $unique,
                'name' => 'idx_' . $table . '_' . $idxName,
                'cols' => $idxCols,
            ];
            continue;
        }

        if (!preg_match('/^`?([a-zA-Z0-9_]+)`?\s+(.+)$/i', $line, $cm)) {
            continue;
        }
        $colName = $cm[1];
        $rest = $cm[2];

        $notNull = (bool) preg_match('/\bNOT\s+NULL\b/i', $rest);
        $auto = (bool) preg_match('/\bAUTO_INCREMENT\b/i', $rest);

        // Default
        $default = null;
        if (preg_match('/\bDEFAULT\s+((?:\'(?:\\\\\'|[^\'])*\')|(?:\"(?:\\\\\"|[^\"])*\")|(?:[^,\s]+(?:\([^)]*\))?))/i', $rest, $dm)) {
            $default = $dm[1];
            $default = preg_replace('/^current_timestamp(?:\(\))?$/i', 'CURRENT_TIMESTAMP', $default) ?? $default;
            if (strcasecmp($default, 'NULL') === 0) {
                $default = 'NULL';
            }
        }

        // Type mapping
        $type = 'TEXT';
        if (preg_match('/\b(TINY|SMALL|MEDIUM|BIG)?INT\b/i', $rest)
            || preg_match('/\bBOOL/i', $rest)
            || preg_match('/\bBIT\b/i', $rest)
        ) {
            $type = 'INTEGER';
        } elseif (preg_match('/\b(DECIMAL|NUMERIC|FLOAT|DOUBLE|REAL)\b/i', $rest)) {
            $type = 'REAL';
        } elseif (preg_match('/\b(BLOB|BINARY|VARBINARY|BYTE)\b/i', $rest)) {
            $type = 'BLOB';
        } else {
            $type = 'TEXT'; // varchar, char, text, json, enum, date, datetime, timestamp, set
        }

        $cols[] = [
            'name' => $colName,
            'type' => $type,
            'not_null' => $notNull,
            'auto' => $auto,
            'default' => $default,
        ];
    }

    // Build CREATE
    $colSql = [];
    foreach ($cols as $c) {
        $isIntPk = ($c['auto'] || (count($primaryCols) === 1 && $primaryCols[0] === $c['name'] && $c['type'] === 'INTEGER'));
        if ($isIntPk && count($primaryCols) <= 1) {
            $colSql[] = '  "' . $c['name'] . '" INTEGER PRIMARY KEY AUTOINCREMENT';
            $primaryCols = []; // consumed
            continue;
        }
        $piece = '  "' . $c['name'] . '" ' . $c['type'];
        if ($c['not_null'] && $c['default'] !== 'NULL') {
            $piece .= ' NOT NULL';
        }
        if ($c['default'] !== null) {
            $piece .= ' DEFAULT ' . $c['default'];
        }
        $colSql[] = $piece;
    }

    if ($primaryCols !== []) {
        $pk = array_map(static fn ($c) => '"' . $c . '"', $primaryCols);
        $colSql[] = '  PRIMARY KEY (' . implode(', ', $pk) . ')';
    }

    $create = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (
' . implode(",\n", $colSql) . '
);';

    $indexSql = [];
    foreach ($indexes as $idx) {
        $u = $idx['unique'] ? 'UNIQUE ' : '';
        $colsList = array_map(static function ($c) {
            $c = trim($c);
            return '"' . $c . '"';
        }, explode(',', $idx['cols']));
        $indexSql[] = 'CREATE ' . $u . 'INDEX IF NOT EXISTS "' . $idx['name'] . '" ON "' . $table . '" (' . implode(', ', $colsList) . ');';
    }

    return ['table' => $table, 'create' => $create, 'indexes' => $indexSql];
}

// Split CREATE TABLE statements
$raw = preg_replace('/\/\*!.*?\*\//s', '', $raw) ?? $raw;
$parts = preg_split('/(?=CREATE\s+TABLE\s+)/i', $raw) ?: [];
$tables = 0;
$indexCount = 0;
$out = [];
$out[] = '-- RATEB ERP Branch SQLite schema (Phase B)';
$out[] = '-- Generated from MySQL dump — do not hand-edit; regenerate via hybrid-phase-b-generate-sqlite-schema.php';
$out[] = '-- Foreign keys omitted (enforced in application layer). WAL applied at connection.';
$out[] = 'PRAGMA foreign_keys=OFF;';
$out[] = '';

foreach ($parts as $part) {
    $part = trim($part);
    if ($part === '' || !preg_match('/^CREATE\s+TABLE/i', $part)) {
        continue;
    }
    // cut at first semicolon ending create
    $semi = strpos($part, ';');
    if ($semi !== false) {
        $part = substr($part, 0, $semi + 1);
    }
    $converted = rateb_mysql_create_to_sqlite($part);
    if ($converted === null) {
        continue;
    }
    $out[] = $converted['create'];
    $out[] = '';
    foreach ($converted['indexes'] as $idx) {
        $out[] = $idx;
        $indexCount++;
    }
    if ($converted['indexes'] !== []) {
        $out[] = '';
    }
    $tables++;
}

$out[] = 'PRAGMA foreign_keys=ON;';
$out[] = '';

if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

$payload = implode("\n", $out);
file_put_contents($outFile, $payload);
echo "Tables: {$tables}\nIndexes: {$indexCount}\nOutput: {$outFile}\nBytes: " . strlen($payload) . PHP_EOL;

if ($tables < 100) {
    fwrite(STDERR, "ERROR: expected hundreds of tables, got {$tables}\n");
    exit(1);
}

echo "OK\n";
exit(0);
