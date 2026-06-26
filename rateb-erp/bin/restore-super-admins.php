<?php
declare(strict_types=1);

/**
 * CLI — restore platform super-admin accounts only.
 *
 *   php bin/restore-super-admins.php --forensic
 *   php bin/restore-super-admins.php --restore
 *   php bin/restore-super-admins.php --restore --no-reset-passwords
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/bin/SuperAdminRestoreRunner.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$forensic = in_array('--forensic', $argv, true);
$restore = in_array('--restore', $argv, true);
$resetPw = !in_array('--no-reset-passwords', $argv, true);

if (!$forensic && !$restore) {
    fwrite(STDERR, "Usage: php bin/restore-super-admins.php --forensic | --restore [--no-reset-passwords]\n");
    exit(1);
}

$runner = new SuperAdminRestoreRunner();
try {
    $report = $restore ? $runner->restore($resetPw) : $runner->forensic();
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(empty($report['errors']) ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
