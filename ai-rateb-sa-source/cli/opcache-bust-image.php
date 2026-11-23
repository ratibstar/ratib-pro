<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
$files = [
    $root . '/app/plans.php',
    $root . '/app/ai/image.php',
    $root . '/campaign.php',
];
if (function_exists('opcache_reset')) {
    echo opcache_reset() ? "opcache_reset=ok\n" : "opcache_reset=fail\n";
}
if (!function_exists('opcache_invalidate')) {
    echo "opcache_invalidate=unavailable\n";
    exit;
}
foreach ($files as $file) {
    if (!is_file($file)) {
        echo "missing=$file\n";
        continue;
    }
    opcache_invalidate($file, true);
    echo 'invalidated=' . basename($file) . "\n";
}
