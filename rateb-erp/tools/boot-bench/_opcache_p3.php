<?php
$files = [
    '/home/admin/domains/rateb.sa/public_html/rateb-erp/views/layouts/main.php',
    '/home/admin/domains/rateb.sa/public_html/rateb-erp/config/app.php',
    '/home/admin/domains/rateb.sa/public_html/rateb-erp/views/partials/sidebar-nav.php',
];
foreach ($files as $f) {
    $ok = function_exists('opcache_invalidate') ? opcache_invalidate($f, true) : null;
    echo $f . ' invalidate=' . var_export($ok, true) . "\n";
    @touch($f);
}
if (function_exists('opcache_reset')) {
    echo 'reset=' . var_export(opcache_reset(), true) . "\n";
}
$st = function_exists('opcache_get_status') ? opcache_get_status(false) : null;
echo 'enabled=' . var_export($st['opcache_enabled'] ?? null, true) . "\n";
echo 'validate=' . ini_get('opcache.validate_timestamps') . "\n";
echo 'revalidate=' . ini_get('opcache.revalidate_freq') . "\n";
// Confirm file content
echo 'main_has_critical=' . (str_contains((string)file_get_contents($files[0]), 'rateb-critical-shell') ? 'yes' : 'no') . "\n";
echo 'build=' . (preg_match("/RATEB_ASSET_BUILD',\s*'([^']+)'/", (string)file_get_contents($files[1]), $m) ? $m[1] : '?') . "\n";
