<?php
declare(strict_types=1);

/**
 * Restore RATEB ERP database from gzip backup.
 * Usage: php bin/erp-restore.php /path/to/backup.sql.gz
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/app/services/Logger.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php bin/erp-restore.php /path/to/backup.sql.gz\n");
    exit(1);
}

$dbName = \Rateb\App\Core\Database::resolvedDatabaseName();
$host = getenv('RATEB_ERP_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$user = getenv('RATEB_ERP_DB_USER') ?: getenv('DB_USER') ?: '';
$pass = getenv('RATEB_ERP_DB_PASS') ?: getenv('DB_PASS') ?: '';

$cmd = sprintf(
    'gunzip -c %s | mysql --host=%s --user=%s %s %s',
    escapeshellarg($file),
    escapeshellarg($host),
    escapeshellarg($user),
    $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
    escapeshellarg($dbName)
);
exec($cmd, $output, $code);
if ($code !== 0) {
    Rateb\App\Services\Logger::error('Restore failed', ['output' => implode("\n", $output)]);
    exit(1);
}
echo "Restored from: {$file}\n";
