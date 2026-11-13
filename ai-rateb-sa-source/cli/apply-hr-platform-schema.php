<?php
declare(strict_types=1);

/**
 * CLI: RATEB_AI_DB_PASSWORD_FILE=/path/to/secret php cli/apply-hr-platform-schema.php
 */
$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require_once $root . '/migrations/platform_settings_schema.php';
require_once $root . '/migrations/company_hr_schema.php';

ensure_platform_settings_schema($pdo);
ensure_company_hr_schema($pdo);

$tables = ['platform_settings', 'hr_departments', 'hr_employees', 'hr_attendance', 'hr_leave_requests', 'hr_documents', 'hr_payroll_records'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    if (!$stmt->fetchColumn()) {
        fwrite(STDERR, "MISSING TABLE: {$table}\n");
        exit(1);
    }
    $idx = $pdo->query("SHOW INDEX FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    echo "{$table}: " . count($idx) . " index rows\n";
}

echo "OK schema applied\n";
