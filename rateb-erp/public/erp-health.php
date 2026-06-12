<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

define('RATEB_ROOT', str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)));
define('RATIB_ENV_NO_SESSION', true);

$steps = [];
try {
    require_once RATEB_ROOT . '/config/app.php';
    $steps[] = 'app.php OK';

    require_once RATEB_ROOT . '/config/database.php';
    $steps[] = 'database.php OK (DB_HOST=' . (defined('DB_HOST') ? DB_HOST : '?') . ', ERP DB=' . (defined('RATEB_DB_NAME') ? RATEB_DB_NAME : '?') . ')';

    require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
    $steps[] = 'Bootstrap OK';

    $pdo = \Rateb\App\Core\Database::connection();
    $steps[] = 'DB connection OK (' . \Rateb\App\Core\Database::resolvedDatabaseName() . ')';

    echo "RATEB ERP health: OK\n";
    foreach ($steps as $line) {
        echo '- ' . $line . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "RATEB ERP health: FAIL\n";
    foreach ($steps as $line) {
        echo '- ' . $line . "\n";
    }
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
