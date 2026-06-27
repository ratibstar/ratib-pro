<?php
/**
 * DIAGNOSTIC: Test Control Panel DB connection (development/ops only).
 * Requires environment variables — no hardcoded credentials.
 *
 * Set before running:
 *   CONTROL_DB_HOST, CONTROL_DB_USER, CONTROL_DB_PASS, CONTROL_DB_NAME
 *   (falls back to DB_HOST, DB_USER, DB_PASS, CONTROL_PANEL_DB_NAME)
 *
 * Do not expose on production without token gate. Prefer CLI: php config/test-control-db.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Forbidden — run via CLI only.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

$db_host = getenv('CONTROL_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$db_port = (int) (getenv('CONTROL_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
$db_user = getenv('CONTROL_DB_USER') ?: getenv('DB_USER') ?: '';
$db_pass = getenv('CONTROL_DB_PASS') ?: getenv('DB_PASS') ?: '';
$db_name = getenv('CONTROL_DB_NAME') ?: getenv('CONTROL_PANEL_DB_NAME') ?: getenv('DB_NAME') ?: '';

if ($db_user === '' || $db_name === '') {
    fwrite(STDERR, "Set CONTROL_DB_USER and CONTROL_DB_NAME (or DB_USER / CONTROL_PANEL_DB_NAME).\n");
    exit(1);
}

echo "=== Control Panel DB Connection Test ===\n\n";
echo "Host: $db_host\n";
echo "Port: $db_port\n";
echo "User: $db_user\n";
echo "DB:   $db_name\n\n";

echo "Attempting connection with localhost...\n";
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) {
    echo "FAILED: " . $conn->connect_error . "\n\n";
    echo "Trying 127.0.0.1...\n";
    $conn = @new mysqli('127.0.0.1', $db_user, $db_pass, $db_name, $db_port);
}
if (!$conn->connect_error) {
    echo "SUCCESS!\n";
    $conn->set_charset('utf8mb4');
    $r = $conn->query('SELECT COUNT(*) as c FROM control_admins');
    echo 'control_admins rows: ' . ($r ? $r->fetch_assoc()['c'] : '?') . "\n";
    $conn->close();
    exit(0);
}

echo 'FAILED: ' . $conn->connect_error . "\n";
exit(1);
