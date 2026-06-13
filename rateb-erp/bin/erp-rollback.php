<?php
declare(strict_types=1);

define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/app/services/MigrationService.php';
require_once RATEB_ROOT . '/app/services/Logger.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$log = (new Rateb\App\Services\MigrationService())->rollbackLast();
foreach ($log as $line) {
    echo $line . PHP_EOL;
}
