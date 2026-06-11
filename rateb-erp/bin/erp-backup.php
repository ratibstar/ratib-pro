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
Rateb\App\Services\Logger::info('Backup created', ['file' => $file]);
echo 'Backup: ' . $file . PHP_EOL;
