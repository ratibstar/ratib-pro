#!/usr/bin/env php
<?php
/**
 * Phase 7 — Accounting Control Center repository validation.
 * Usage: php scripts/validate-accounting-control.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$warnings = [];
$pass = 0;

function check(bool $ok, string $label, array &$errors, array &$warnings, bool $warn = false): void
{
    global $pass;
    if ($ok) {
        $pass++;
        return;
    }
    if ($warn) {
        $warnings[] = $label;
    } else {
        $errors[] = $label;
    }
}

$phpFiles = array_merge(
    glob($root . '/app/Accounting/Admin/**/*.php') ?: [],
    glob($root . '/rateb-erp/app/controllers/Admin/AccountingControlController.php') ?: [],
    glob($root . '/rateb-erp/views/admin/accounting-control/**/*.php') ?: [],
    glob($root . '/api/accounting/*.php') ?: [],
    glob($root . '/api/accounting/control/*.php') ?: [],
);

foreach ($phpFiles as $file) {
    $out = [];
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    check($code === 0, 'PHP syntax: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file), $errors, $warnings);
}

$sections = ['dashboard', 'events', 'replay', 'audit', 'projections', 'consolidation', 'drift', 'reconciliation', 'integrity', 'settings', 'health', 'timeline', 'notifications', 'diagnostics'];
foreach ($sections as $sec) {
    $path = $root . '/rateb-erp/views/admin/accounting-control/sections/' . $sec . '.php';
    check(is_file($path), 'View section: ' . $sec, $errors, $warnings);
}

$assets = ['control-center.js', 'control-center.css', 'control-center-phase7.js'];
foreach ($assets as $a) {
    check(is_file($root . '/rateb-erp/public/assets/accounting-control/' . $a), 'Asset: ' . $a, $errors, $warnings);
}

check(is_file($root . '/rateb-erp/app/controllers/Admin/AccountingControlController.php'), 'Controller', $errors, $warnings);
check(is_file($root . '/app/Accounting/Admin/Services/AccountingControlPhase7Service.php'), 'Phase7Service', $errors, $warnings);
check(is_file($root . '/app/Accounting/Admin/Services/AccountingControlExportService.php'), 'ExportService', $errors, $warnings);

$routes = file_get_contents($root . '/rateb-erp/routes/web.php') ?: '';
check(str_contains($routes, 'accounting-control/timeline'), 'Route: timeline', $errors, $warnings);
check(str_contains($routes, 'accounting-control/diagnostics'), 'Route: diagnostics', $errors, $warnings);

$nav = file_get_contents($root . '/rateb-erp/views/partials/sidebar-ops-nav.php') ?: '';
check(str_contains($nav, 'accounting-control'), 'Sidebar link', $errors, $warnings);
check(substr_count($nav, 'accounting-control') === 1, 'No duplicate sidebar entry', $errors, $warnings, true);

$lang = file_get_contents($root . '/rateb-erp/config/lang/en.php') ?: '';
check(str_contains($lang, 'accounting_control_timeline'), 'i18n: timeline', $errors, $warnings);
check(str_contains($lang, 'accounting_control_diagnostics'), 'i18n: diagnostics', $errors, $warnings);

$docs = ['Accounting-Control-Center.md', 'Architecture.md', 'Deployment.md', 'Operations.md', 'Permissions.md', 'API.md', 'Troubleshooting.md'];
foreach ($docs as $d) {
    check(is_file($root . '/docs/accounting-control/' . $d), 'Doc: ' . $d, $errors, $warnings);
}

echo "Accounting Control Center Validation\n";
echo "PASS checks: {$pass}\n";
echo "WARNINGS: " . count($warnings) . "\n";
foreach ($warnings as $w) {
    echo "  WARN: {$w}\n";
}
echo "ERRORS: " . count($errors) . "\n";
foreach ($errors as $e) {
    echo "  FAIL: {$e}\n";
}

exit(count($errors) > 0 ? 1 : 0);
