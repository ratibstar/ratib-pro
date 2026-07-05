<?php
declare(strict_types=1);

/**
 * P7-1 WP6 — idempotent backfill: assign main branch to branch-restricted users
 * with zero rateb_user_branches rows.
 *
 * Usage:
 *   php bin/backfill-user-branch-assignments.php --dry-run
 *   php bin/backfill-user-branch-assignments.php
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once __DIR__ . '/BackfillUserBranchAssignmentsRunner.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
if (in_array('--help', $argv ?? [], true) || in_array('-h', $argv ?? [], true)) {
    fwrite(STDOUT, "Usage: php bin/backfill-user-branch-assignments.php [--dry-run]\n");
    exit(0);
}

try {
    $stats = (new BackfillUserBranchAssignmentsRunner())->run($dryRun);

    echo "RATEB ERP — backfill user branch assignments\n";
    echo str_repeat('=', 44) . "\n";
    echo 'Mode:               ' . ($dryRun ? 'dry-run' : 'execute') . "\n";
    echo 'Companies processed:' . $stats['companies_processed'] . "\n";
    echo 'Users scanned:      ' . $stats['users_scanned'] . "\n";
    echo 'Users updated:      ' . $stats['users_updated'] . "\n";
    echo 'Users skipped:      ' . $stats['users_skipped'] . "\n";
    echo 'Elapsed (s):        ' . $stats['elapsed_seconds'] . "\n";
    if (($stats['errors'] ?? []) !== []) {
        echo str_repeat('-', 44) . "\n";
        foreach ($stats['errors'] as $err) {
            echo 'WARN: ' . $err . "\n";
        }
    }

    exit(($stats['errors'] ?? []) !== [] && !$dryRun ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
