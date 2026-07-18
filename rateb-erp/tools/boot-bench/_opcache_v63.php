<?php
$files = [
    '/home/admin/domains/rateb.sa/public_html/rateb-erp/views/layouts/main.php',
    '/home/admin/domains/rateb.sa/public_html/rateb-erp/config/app.php',
];
foreach ($files as $f) {
    $ok = function_exists('opcache_invalidate') ? opcache_invalidate($f, true) : null;
    echo $f . ' invalidate=' . var_export($ok, true) . "\n";
    @touch($f);
}
if (function_exists('opcache_reset')) {
    echo 'reset=' . var_export(opcache_reset(), true) . "\n";
}
$app = @file_get_contents('/home/admin/domains/rateb.sa/public_html/rateb-erp/config/app.php');
echo 'build=' . (preg_match("/RATEB_ASSET_BUILD',\s*'([^']+)'/", $app, $m) ? $m[1] : '?') . "\n";
$main = @file_get_contents('/home/admin/domains/rateb.sa/public_html/rateb-erp/views/layouts/main.php');
echo 'has_v3=' . (str_contains($main, "=== '3'") ? '1' : '0') . "\n";
echo 'has_accordion=' . (str_contains($main, 'closeSiblingGroups') ? '1' : '0') . "\n";
@unlink(__FILE__);
