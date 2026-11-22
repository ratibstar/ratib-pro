<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
foreach ([
    $root . '/app/auth/reset-lib.php',
    $root . '/app/auth/forgot-password.php',
    $root . '/forgot-password.php',
    $root . '/public/assets/js/app.js',
] as $file) {
    if (is_file($file) && function_exists('opcache_invalidate')) {
        opcache_invalidate($file, true);
        echo 'ok=' . basename($file) . "\n";
    }
}
