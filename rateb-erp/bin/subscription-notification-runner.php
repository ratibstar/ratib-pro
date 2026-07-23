<?php
declare(strict_types=1);

/**
 * RATEB ERP — Subscription notification generator (Phase 4).
 *
 * Creates eligible rows in rateb_subscription_notification_history only.
 * Does NOT send email/SMS/push, show UI, block access, or install cron.
 *
 * Usage:
 *   php bin/subscription-notification-runner.php
 *   php bin/subscription-notification-runner.php --dry-run
 *   php bin/subscription-notification-runner.php --batch-size=50 --today=2026-07-23
 *
 * Cron (documentation only — do not install automatically):
 *   15 6 * * * cd /path/to/rateb-erp && php bin/subscription-notification-runner.php >> storage/logs/subscription-notifications.log 2>&1
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', $root);
}

require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

$module = RATEB_ROOT . '/modules/subscription/SubscriptionModule.php';
if (!is_file($module)) {
    fwrite(STDERR, "Subscription module missing.\n");
    exit(1);
}
require_once $module;
\Rateb\App\Subscription\SubscriptionModule::init();

$argvList = $argv ?? [];
if (in_array('--help', $argvList, true) || in_array('-h', $argvList, true)) {
    fwrite(STDOUT, <<<TXT
RATEB Subscription Notification Runner (Phase 4)

Usage:
  php bin/subscription-notification-runner.php [options]

Options:
  --dry-run              Evaluate only; do not insert history rows
  --batch-size=N         Tenants per batch (default 100, max 500)
  --today=YYYY-MM-DD     Override evaluation date (UTC calendar day)
  --max-batches=N        Stop after N batches (testing)
  -h, --help             Show this help

Safe to run multiple times per day (deduplicated history).

TXT);
    exit(0);
}

$options = [
    'dry_run' => in_array('--dry-run', $argvList, true),
];

foreach ($argvList as $arg) {
    if (str_starts_with($arg, '--batch-size=')) {
        $options['batch_size'] = (int) substr($arg, strlen('--batch-size='));
    } elseif (str_starts_with($arg, '--today=')) {
        $options['today'] = substr($arg, strlen('--today='));
    } elseif (str_starts_with($arg, '--max-batches=')) {
        $options['max_batches'] = (int) substr($arg, strlen('--max-batches='));
    }
}

try {
    $stats = \Rateb\App\Subscription\SubscriptionNotificationJob::run($options);

    echo "RATEB ERP — subscription notification generator\n";
    echo str_repeat('=', 48) . "\n";
    echo 'Today:       ' . $stats['today'] . "\n";
    echo 'Mode:        ' . (!empty($stats['dry_run']) ? 'dry-run' : 'execute') . "\n";
    echo 'Scanned:     ' . $stats['scanned'] . "\n";
    echo 'Eligible:    ' . $stats['eligible'] . "\n";
    echo 'Inserted:    ' . $stats['inserted'] . "\n";
    echo 'Skipped:     ' . $stats['skipped'] . "\n";
    echo 'Declined:    ' . $stats['declined'] . "\n";
    echo 'Batches:     ' . $stats['batches'] . "\n";
    echo 'Elapsed (s): ' . $stats['elapsed_seconds'] . "\n";

    if (($stats['errors'] ?? []) !== []) {
        echo str_repeat('-', 48) . "\n";
        foreach ($stats['errors'] as $err) {
            echo 'WARN: ' . $err . "\n";
        }
    }

    $hasErrors = ($stats['errors'] ?? []) !== [];
    exit($hasErrors ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    error_log('RATEB subscription-notification-runner: ' . $e->getMessage());
    exit(1);
}
