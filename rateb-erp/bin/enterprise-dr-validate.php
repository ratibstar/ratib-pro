<?php
declare(strict_types=1);

/**
 * Phase 6 — Disaster Recovery validation (backup/restore readiness).
 *
 * Usage: php bin/enterprise-dr-validate.php [--json]
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

$json = in_array('--json', $argv ?? [], true);
$backupDir = RATEB_ROOT . '/storage/backups';
$uploadsDir = RATEB_ROOT . '/storage/uploads';

$report = [
    'database_backup' => [
        'script' => is_file(RATEB_ROOT . '/bin/erp-backup.php'),
        'verify_script' => is_file(RATEB_ROOT . '/bin/erp-backup-verify.php'),
        'restore_script' => is_file(RATEB_ROOT . '/bin/erp-restore.php'),
        'backup_dir_writable' => is_dir($backupDir) && is_writable($backupDir),
        'latest_backup' => null,
        'latest_size_bytes' => 0,
    ],
    'file_backup' => [
        'uploads_dir_exists' => is_dir($uploadsDir),
        'uploads_writable' => is_dir($uploadsDir) && is_writable($uploadsDir),
    ],
    'rto_rpo' => [
        'rto_target_minutes' => 60,
        'rpo_target_minutes' => 1440,
        'rto_estimated_minutes' => null,
        'rpo_estimated_minutes' => null,
        'notes' => 'Run erp-backup.php + erp-restore.php on staging to measure actual RTO/RPO.',
    ],
    'checked_at' => date('c'),
];

$latest = null;
$latestSize = 0;
if (is_dir($backupDir)) {
    foreach (glob($backupDir . '/erp-*.sql.gz') ?: [] as $f) {
        $mtime = filemtime($f) ?: 0;
        if ($latest === null || $mtime > ($report['database_backup']['latest_mtime'] ?? 0)) {
            $latest = basename($f);
            $latestSize = filesize($f) ?: 0;
            $report['database_backup']['latest_mtime'] = $mtime;
        }
    }
}
$report['database_backup']['latest_backup'] = $latest;
$report['database_backup']['latest_size_bytes'] = $latestSize;

if ($latest && isset($report['database_backup']['latest_mtime'])) {
    $ageMinutes = (int) ((time() - (int) $report['database_backup']['latest_mtime']) / 60);
    $report['rto_rpo']['rpo_estimated_minutes'] = $ageMinutes;
    unset($report['database_backup']['latest_mtime']);
}

$report['passed'] =
    $report['database_backup']['script']
    && $report['database_backup']['restore_script']
    && $report['database_backup']['backup_dir_writable'];

if ($json) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "Disaster Recovery Validation\n";
    echo "DB backup script: " . ($report['database_backup']['script'] ? 'yes' : 'no') . "\n";
    echo "Restore script: " . ($report['database_backup']['restore_script'] ? 'yes' : 'no') . "\n";
    echo "Backup dir writable: " . ($report['database_backup']['backup_dir_writable'] ? 'yes' : 'no') . "\n";
    echo "Latest backup: " . ($latest ?? 'none') . "\n";
    echo "RPO estimate (minutes since last backup): " . ($report['rto_rpo']['rpo_estimated_minutes'] ?? 'n/a') . "\n";
    echo "Overall: " . ($report['passed'] ? 'PASS (structural)' : 'FAIL') . "\n";
}

exit($report['passed'] ? 0 : 1);
