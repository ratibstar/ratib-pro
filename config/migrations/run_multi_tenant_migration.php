<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/migrations/run_multi_tenant_migration.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/migrations/run_multi_tenant_migration.php`.
 */
/**
 * Safe Multi-Tenant Migration Runner
 *
 * Run from CLI: php config/migrations/run_multi_tenant_migration.php
 * Or from browser (remove after use - security)
 *
 * Checks if columns exist before adding. Does NOT drop data.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Minimal bootstrap
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (file_exists(__DIR__ . '/../../includes/config.php')) {
    require_once __DIR__ . '/../../includes/config.php';
}
$conn = $GLOBALS['conn'] ?? null;
if (!$conn && defined('DB_HOST')) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT ?? 3306);
    $conn->set_charset('utf8mb4');
}
if (!$conn || !($conn instanceof mysqli)) {
    die("Database connection failed. Run the SQL file first to create countries table.\n");
}
$chkCountries = @$conn->query("SHOW TABLES LIKE 'countries'");
if (!$chkCountries || $chkCountries->num_rows === 0) {
    die("Table 'countries' does not exist. Run config/migrations/multi_tenant_001_countries.sql first.\n");
}

$tables = ['users', 'roles', 'activity_logs', 'system_config'];
$done = [];
$skipped = [];

foreach ($tables as $table) {
    $chk = @$conn->query("SHOW TABLES LIKE '$table'");
    if (!$chk || $chk->num_rows === 0) {
        $skipped[] = "$table (table does not exist)";
        continue;
    }
    $col = @$conn->query("SHOW COLUMNS FROM $table LIKE 'country_id'");
    if ($col && $col->num_rows > 0) {
        $skipped[] = "$table (country_id already exists)";
        continue;
    }
    $conn->query("ALTER TABLE $table ADD COLUMN country_id INT UNSIGNED NOT NULL DEFAULT 1");
    $conn->query("ALTER TABLE $table ADD KEY idx_country_id (country_id)");
    $done[] = $table;
}

echo "Migration complete.\n";
echo "Added country_id to: " . (empty($done) ? 'none' : implode(', ', $done)) . "\n";
echo "Skipped: " . implode('; ', $skipped) . "\n";
echo "\nRun the SQL file for: countries table creation, foreign keys, and seed data.\n";
