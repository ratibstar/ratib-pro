<?php
declare(strict_types=1);

/**
 * RATEB ERP database backup — run nightly via cron.
 * Usage: php bin/erp-backup.php
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/app/services/Logger.php';
require_once RATEB_ROOT . '/app/services/AutomationSettings.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$dbName = \Rateb\App\Core\Database::resolvedDatabaseName();
$host = getenv('RATEB_ERP_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$user = getenv('RATEB_ERP_DB_USER') ?: getenv('DB_USER') ?: '';
$pass = getenv('RATEB_ERP_DB_PASS') ?: getenv('DB_PASS') ?: '';

$dir = RATEB_ROOT . '/storage/backups';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
$file = $dir . '/erp-' . $dbName . '-' . date('Ymd-His') . '.sql.gz';
$cmd = sprintf(
    'mysqldump --host=%s --user=%s %s %s 2>&1 | gzip > %s',
    escapeshellarg($host),
    escapeshellarg($user),
    $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
    escapeshellarg($dbName),
    escapeshellarg($file)
);

exec($cmd, $output, $code);
if ($code !== 0 || !is_file($file)) {
    Rateb\App\Services\Logger::error('Backup failed', ['output' => implode("\n", $output), 'code' => $code]);
    exit(1);
}

$size = filesize($file) ?: 0;
if ($size < 100) {
    Rateb\App\Services\Logger::error('Backup verification failed — file too small', ['file' => $file, 'size' => $size]);
    @unlink($file);
    exit(1);
}

$uploadsDir = RATEB_ROOT . '/storage/uploads';
$filesArchive = $dir . '/erp-files-' . date('Ymd-His') . '.tar.gz';
if (is_dir($uploadsDir)) {
    $tarCmd = sprintf('tar -czf %s -C %s uploads 2>&1', escapeshellarg($filesArchive), escapeshellarg(RATEB_ROOT . '/storage'));
    exec($tarCmd, $tarOut, $tarCode);
    if ($tarCode !== 0) {
        Rateb\App\Services\Logger::warning('File backup failed', ['output' => implode("\n", $tarOut)]);
    }
}

$retentionDays = Rateb\App\Services\AutomationSettings::backupRetentionDays();
$cutoff = time() - ($retentionDays * 86400);
foreach (glob($dir . '/*') ?: [] as $old) {
    if (is_file($old) && filemtime($old) < $cutoff) {
        @unlink($old);
    }
}

(new Rateb\App\Services\AutomationHealthService())->recordCronRun('erp-backup', [
    'db_file' => basename($file),
    'db_size' => $size,
    'files_archive' => is_file($filesArchive) ? basename($filesArchive) : '',
], 1440);

Rateb\App\Services\Logger::info('Backup created', ['file' => $file, 'size' => $size]);
echo 'Backup: ' . $file . PHP_EOL;
