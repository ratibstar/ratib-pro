<?php
declare(strict_types=1);

/**
 * Run RATEB ERP migrations via CLI or browser (protect in production).
 * Usage: php migrations/run.php
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

$pdo = Rateb\App\Core\Database::connection();
$files = glob(RATEB_ROOT . '/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        continue;
    }
    echo 'Running ' . basename($file) . PHP_EOL;
    $pdo->exec($sql);
    echo 'Done.' . PHP_EOL;
}

echo 'All migrations completed.' . PHP_EOL;
