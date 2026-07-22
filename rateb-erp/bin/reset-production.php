<?php
declare(strict_types=1);

/**
 * RATEB ERP — Production reset (pre-GA go-live).
 *
 * Removes all business/transactional data while preserving schema, migrations,
 * super-admin, RBAC, templates, CMS marketing content, and system settings.
 *
 * NEVER run without a full backup. Does NOT run automatically.
 *
 * Usage:
 *   php bin/reset-production.php --dry-run
 *   php bin/reset-production.php --validate
 *   php bin/reset-production.php --confirm=RESET-PRODUCTION
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once RATEB_ROOT . '/bin/ProductionResetRunner.php';

// --- CLI ---
$dryRun = in_array('--dry-run', $argv ?? [], true);
$validate = in_array('--validate', $argv ?? [], true);
$confirm = '';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--confirm=')) {
        $confirm = substr($arg, 10);
    }
}

if ($validate) {
    $issues = ProductionResetRunner::validateProcedure();
    echo "Reset procedure validation\n";
    if ($issues === []) {
        echo "OK — script structure valid. Run with --dry-run before go-live.\n";
        exit(0);
    }
    foreach ($issues as $issue) {
        echo "ISSUE: {$issue}\n";
    }
    exit(1);
}

try {
    $pdo = Rateb\App\Core\Database::connection();
} catch (\Throwable $e) {
    fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

if (!$dryRun && $confirm !== ProductionResetRunner::CONFIRM_PHRASE) {
    fwrite(STDERR, "\n");
    fwrite(STDERR, "╔══════════════════════════════════════════════════════════════╗\n");
    fwrite(STDERR, "║  DANGER: This permanently deletes ALL business data.        ║\n");
    fwrite(STDERR, "║  Take a full backup before proceeding.                      ║\n");
    fwrite(STDERR, "╚══════════════════════════════════════════════════════════════╝\n");
    fwrite(STDERR, "\nRequired: php bin/reset-production.php --confirm=" . ProductionResetRunner::CONFIRM_PHRASE . "\n");
    fwrite(STDERR, "Preview:  php bin/reset-production.php --dry-run\n\n");
    exit(1);
}

$runner = new ProductionResetRunner($pdo);
$runner->run($dryRun);

$report = $runner->report();
$reportPath = RATEB_ROOT . '/storage/logs/reset-production-' . date('Ymd-His') . '.json';
$logDir = dirname($reportPath);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
if ($dryRun) {
    echo "\nDRY-RUN complete — no data modified.\n";
} else {
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\nEXECUTE complete — report: {$reportPath}\n";
}

exit(empty($report['errors']) ? 0 : 1);
