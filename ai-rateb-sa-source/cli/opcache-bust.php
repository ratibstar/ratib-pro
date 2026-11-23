<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
$files = [
    $root . '/app/nav.php',
    $root . '/app/public-gate.php',
    $root . '/contact.php',
    $root . '/privacy.php',
    $root . '/terms.php',
    $root . '/pricing.php',
    $root . '/index.php',
    $root . '/dashboard.php',
    $root . '/admin/lib.php',
    $root . '/admin/subscriptions.php',
    $root . '/admin/payments.php',
    $root . '/public/assets/js/app.js',
    $root . '/public/assets/css/app.css',
];
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
echo 'footer_exists=' . (function_exists('rateb_site_footer') ? 'before-require' : 'no') . "\n";
require_once $root . '/app/nav.php';
echo 'footer_exists=' . (function_exists('rateb_site_footer') ? 'yes' : 'no') . "\n";
