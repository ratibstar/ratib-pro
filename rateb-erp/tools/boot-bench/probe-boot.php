<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
$_SERVER['SCRIPT_NAME'] = '/rateb-erp/public/index.php';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'off';

try {
    require $root . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init($root);
    echo "boot_ok\n";
    echo 'asset=' . rateb_asset('css/main.css') . "\n";
    echo 'bootstrap_css=' . rateb_bootstrap_css() . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
