<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t = (string)($_GET['t'] ?? '');
if (!hash_equals('01114270b6fde045a66fdd9df6e10f28', $t)) {
    http_response_code(404);
    echo "not_found\n";
    exit;
}
$files = [
    dirname(__DIR__) . '/config/app.php',
    dirname(__DIR__) . '/views/layouts/main.php',
    dirname(__DIR__) . '/modules/pos/app/Services/PosOfflineDeviceService.php',
];
foreach ($files as $f) {
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($f, true);
    }
    echo 'invalidate ' . $f . "\n";
}
echo 'reset=' . (function_exists('opcache_reset') ? var_export(opcache_reset(), true) : 'n/a') . "\n";
$app = @file_get_contents(dirname(__DIR__) . '/config/app.php');
echo 'build=' . (preg_match("/RATEB_ASSET_BUILD',\s*'([^']+)'/", (string)$app, $m) ? $m[1] : '?') . "\n";
@unlink(__FILE__);
