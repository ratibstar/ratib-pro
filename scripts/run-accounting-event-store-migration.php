<?php
/**
 * One-shot runner for config/migrations/20260704_accounting_event_store.sql
 * Usage: php scripts/run-accounting-event-store-migration.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config/env/load.php';

$host = defined('DB_HOST') ? (string) DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
$user = defined('DB_USER') ? (string) DB_USER : (getenv('DB_USER') ?: '');
$pass = defined('DB_PASS') ? (string) DB_PASS : (getenv('DB_PASS') ?: '');
$name = defined('DB_NAME') ? (string) DB_NAME : (getenv('DB_NAME') ?: '');
$port = defined('DB_PORT') ? (int) DB_PORT : (int) (getenv('DB_PORT') ?: 3306);

$sqlFile = $root . '/config/migrations/20260704_accounting_event_store.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "SQL file not found: {$sqlFile}\n");
    exit(1);
}

if ($name === '' || $user === '') {
    fwrite(STDERR, "DB_NAME/DB_USER not configured. Set .env or config/env/{host}.php\n");
    exit(1);
}

$mysqli = null;
$pdo = null;

if (class_exists('mysqli')) {
    $mysqli = @new mysqli($host, $user, $pass, $name, $port);
    if ($mysqli->connect_error) {
        fwrite(STDERR, 'mysqli connection failed: ' . $mysqli->connect_error . "\n");
        exit(1);
    }
    $mysqli->set_charset('utf8mb4');
} else {
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        fwrite(STDERR, 'PDO connection failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Could not read SQL file\n");
    exit(1);
}

// Strip comments and split on semicolons for multi-statement execution
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));

$ok = 0;
foreach ($statements as $stmt) {
    if ($stmt === '' || (isset($stmt[0]) && $stmt[0] === '-' && isset($stmt[1]) && $stmt[1] === '-')) {
        continue;
    }
    try {
        if ($mysqli instanceof mysqli) {
            if (!$mysqli->query($stmt)) {
                throw new RuntimeException($mysqli->error);
            }
        } else {
            $pdo->exec($stmt);
        }
        $ok++;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\nStatement: " . substr($stmt, 0, 80) . "...\n");
        exit(1);
    }
}

echo "OK — applied {$ok} statement(s) on database `{$name}` @ {$host}\n";

$tables = ['accounting_events', 'accounting_processed_events', 'accounting_audit_logs'];
foreach ($tables as $t) {
    if ($mysqli instanceof mysqli) {
        $res = $mysqli->query("SHOW TABLES LIKE '{$t}'");
        echo ($res && $res->num_rows > 0 ? '[exists] ' : '[MISSING] ') . $t . "\n";
    } else {
        $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
        echo ($st && $st->fetchColumn() ? '[exists] ' : '[MISSING] ') . $t . "\n";
    }
}

if ($mysqli instanceof mysqli) {
    $mysqli->close();
}
exit(0);
