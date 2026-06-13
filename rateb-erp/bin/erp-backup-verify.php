<?php
declare(strict_types=1);

/**
 * Verify latest (or given) ERP backup without restoring.
 * Usage: php bin/erp-backup-verify.php [path/to/backup.sql.gz]
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/app/services/DeploymentReadinessService.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$file = $argv[1] ?? '';
if ($file === '') {
    $dir = RATEB_ROOT . '/storage/backups';
    $files = is_dir($dir) ? (glob($dir . '/*.sql.gz') ?: []) : [];
    if ($files === []) {
        fwrite(STDERR, "No backups found in storage/backups\n");
        exit(1);
    }
    usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
    $file = $files[0];
}

$check = (new Rateb\App\Services\DeploymentReadinessService())->verifyBackupFile($file);
if (!$check['valid']) {
    fwrite(STDERR, 'INVALID: ' . ($check['error'] ?? 'unknown') . ' — ' . $file . "\n");
    exit(1);
}

echo 'VALID: ' . $file . ' (' . $check['size'] . " bytes)\n";
exit(0);
