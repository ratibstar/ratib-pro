<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/migrations/separate_control_panel_db/03_migrate_data.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/migrations/separate_control_panel_db/03_migrate_data.php`.
 */
/**
 * Migrate control_* tables from outratib_out (Ratib Pro DB) to control_panel_db.
 * Run once after 01_create_database.sql and 02_create_tables.sql.
 *
 * Usage: php 03_migrate_data.php
 *    or: https://out.ratib.sa/config/migrations/separate_control_panel_db/03_migrate_data.php (requires control login)
 *
 * Set env vars or edit below:
 *   RATIB_DB_NAME (source) = outratib_out
 *   CONTROL_PANEL_DB_NAME (dest) = control_panel_db
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sourceDb = getenv('RATIB_DB_NAME') ?: 'outratib_out';
$destDb   = getenv('CONTROL_PANEL_DB_NAME') ?: 'control_panel_db';
$host     = getenv('DB_HOST') ?: 'localhost';
$port     = (int)(getenv('DB_PORT') ?: 3306);
$user     = getenv('DB_USER') ?: 'outratib_out';
$pass     = getenv('DB_PASS') ?: '9s%BpMr1]dfb';

$controlTables = [
    'control_countries',
    'control_agencies',
    'control_admins',
    'control_admin_permissions',
    'control_registration_requests',
    'control_support_chats',
    'control_support_chat_messages',
    'control_accounting_transactions',
    'control_chart_accounts',
    'control_cost_centers',
    'control_bank_guarantees',
    'control_support_payments',
    'control_journal_entries',
    'control_journal_entry_lines',
    'control_expenses',
    'control_receipts',
    'control_disbursement_vouchers',
    'control_electronic_invoices',
    'control_entry_approvals',
    'control_bank_reconciliations',
];

echo "Control Panel DB Migration\n";
echo "Source: $sourceDb -> Dest: $destDb\n\n";

try {
    $src = new mysqli($host, $user, $pass, $sourceDb, $port);
    $src->set_charset('utf8mb4');
} catch (Throwable $e) {
    die("Source DB connection failed: " . $e->getMessage() . "\n");
}

try {
    $dst = new mysqli($host, $user, $pass, $destDb, $port);
    $dst->set_charset('utf8mb4');
} catch (Throwable $e) {
    die("Dest DB connection failed: " . $e->getMessage() . "\n");
}

$migrated = 0;
$skipped  = 0;
$errors   = [];

foreach ($controlTables as $table) {
    $chk = $src->query("SHOW TABLES LIKE '$table'");
    if (!$chk || $chk->num_rows === 0) {
        echo "  [SKIP] $table - not in source\n";
        $skipped++;
        continue;
    }
    $chkDest = $dst->query("SHOW TABLES LIKE '$table'");
    if (!$chkDest || $chkDest->num_rows === 0) {
        echo "  [SKIP] $table - not in dest\n";
        $skipped++;
        continue;
    }
    $colsSrc = [];
    $r = $src->query("SHOW COLUMNS FROM `$table`");
    while ($row = $r->fetch_assoc()) {
        $colsSrc[] = $row['Field'];
    }
    $colsDst = [];
    $r = $dst->query("SHOW COLUMNS FROM `$table`");
    while ($row = $r->fetch_assoc()) {
        $colsDst[] = $row['Field'];
    }
    $common = array_intersect($colsSrc, $colsDst);
    if (empty($common)) {
        echo "  [SKIP] $table - no common columns\n";
        $skipped++;
        continue;
    }
    $colList = '`' . implode('`,`', $common) . '`';
    $dst->query("SET FOREIGN_KEY_CHECKS = 0");
    $dst->query("TRUNCATE TABLE `$table`");
    $dst->query("SET FOREIGN_KEY_CHECKS = 1");
    $res = $src->query("SELECT $colList FROM `$table`");
    if (!$res) {
        $errors[] = "$table: " . $src->error;
        continue;
    }
    $count = 0;
    while ($row = $res->fetch_assoc()) {
        $vals = [];
        foreach ($common as $c) {
            $v = $row[$c];
            $vals[] = ($v === null) ? 'NULL' : "'" . $dst->real_escape_string($v) . "'";
        }
        $sql = "INSERT INTO `$table` ($colList) VALUES (" . implode(',', $vals) . ")";
        if ($dst->query($sql)) {
            $count++;
        }
    }
    echo "  [OK] $table - $count rows\n";
    $migrated++;
}

$src->close();
$dst->close();

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $e) echo "  $e\n";
}

echo "\nDone. Migrated: $migrated tables. Skipped: $skipped.\n";
echo "Next: Update config/env to use CONTROL_PANEL_DB_NAME = $destDb and remove control_* tables from $sourceDb (optional).\n";
