<?php
declare(strict_types=1);

/**
 * Restore or verify RATEB ERP database backup.
 * Usage:
 *   php bin/erp-restore.php /path/to/backup.sql.gz
 *   php bin/erp-restore.php --verify /path/to/backup.sql.gz
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/app/services/Logger.php';
require_once RATEB_ROOT . '/app/services/DeploymentReadinessService.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$verifyOnly = false;
$file = '';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--verify') {
        $verifyOnly = true;
        continue;
    }
    if ($arg !== '' && $file === '') {
        $file = $arg;
    }
}

if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php bin/erp-restore.php [--verify] /path/to/backup.sql.gz\n");
    exit(1);
}

$check = (new Rateb\App\Services\DeploymentReadinessService())->verifyBackupFile($file);
if (!$check['valid']) {
    Rateb\App\Services\Logger::error('Backup verification failed', $check);
    fwrite(STDERR, 'Backup invalid: ' . ($check['error'] ?? 'unknown') . "\n");
    exit(1);
}

echo 'Backup OK: ' . basename($file) . ' (' . $check['size'] . " bytes)\n";

if ($verifyOnly) {
    exit(0);
}

$dbName = \Rateb\App\Core\Database::resolvedDatabaseName();
$host = getenv('RATEB_ERP_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$user = getenv('RATEB_ERP_DB_USER') ?: getenv('DB_USER') ?: '';
$pass = getenv('RATEB_ERP_DB_PASS') ?: getenv('DB_PASS') ?: '';

$isWin = DIRECTORY_SEPARATOR === '\\';
if ($isWin) {
    $sql = '';
    $gz = gzopen($file, 'rb');
    if ($gz === false) {
        fwrite(STDERR, "Cannot open gzip file\n");
        exit(1);
    }
    while (!gzeof($gz)) {
        $sql .= gzread($gz, 65536);
    }
    gzclose($gz);
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'erp-restore-' . uniqid('', true) . '.sql';
    file_put_contents($tmp, $sql);
    $cmd = sprintf(
        'mysql --host=%s --user=%s %s %s < %s',
        escapeshellarg($host),
        escapeshellarg($user),
        $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
        escapeshellarg($dbName),
        escapeshellarg($tmp)
    );
    exec($cmd, $output, $code);
    @unlink($tmp);
} else {
    $cmd = sprintf(
        'gunzip -c %s | mysql --host=%s --user=%s %s %s',
        escapeshellarg($file),
        escapeshellarg($host),
        escapeshellarg($user),
        $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
        escapeshellarg($dbName)
    );
    exec($cmd, $output, $code);
}

if ($code !== 0) {
    Rateb\App\Services\Logger::error('Restore failed', ['output' => implode("\n", $output), 'code' => $code]);
    fwrite(STDERR, "Restore failed (exit {$code})\n");
    exit(1);
}

Rateb\App\Services\Logger::info('Database restored', ['file' => $file]);
echo "Restored from: {$file}\n";
