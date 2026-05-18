<?php
/**
 * Disk vs live HTML diagnostic — delete after fix.
 * https://out.ratib.sa/pages/ratib-live-chrome-check.php
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$root = dirname(__DIR__);
$chrome = $root . '/includes/ratib-home-public-chrome-top.php';
$sync = $root . '/includes/ratib-home-public-nav-sync.php';
$home = $root . '/pages/home.php';

echo "ratib-live-chrome-check\n";
echo 'time=' . gmdate('c') . "\n\n";

foreach (['chrome' => $chrome, 'nav-sync' => $sync, 'home' => $home] as $label => $path) {
    echo "=== {$label}: {$path} ===\n";
    if (!is_file($path)) {
        echo "MISSING\n\n";
        continue;
    }
    echo 'mtime=' . date('c', (int) filemtime($path)) . "\n";
    echo 'bytes=' . filesize($path) . "\n";
    $body = (string) file_get_contents($path);
    echo 'has_onclick=' . (stripos($body, 'onclick=') !== false ? 'yes' : 'no') . "\n";
    echo 'has_go_profile=' . (stripos($body, 'data-ratib-go-profile') !== false ? 'yes' : 'no') . "\n";
    echo 'has_wireProfileLink=' . (stripos($body, 'wireProfileLink') !== false ? 'yes' : 'no') . "\n";
    echo 'has_ratib_nav_sync_profile=' . (stripos($body, 'ratib-nav-sync-profile') !== false ? 'yes' : 'no') . "\n";
    echo "\n";
}

if (function_exists('opcache_invalidate')) {
    foreach ([$chrome, $sync, $home] as $p) {
        if (is_file($p)) {
            opcache_invalidate($p, true);
        }
    }
    echo "opcache_invalidate=done (refresh home page now)\n\n";
} else {
    echo "opcache_invalidate=not available\n\n";
}

echo "=== Live fetch home.php (first 8KB around brand-profile) ===\n";
$live = @file_get_contents('https://out.ratib.sa/pages/home.php', false, stream_context_create([
    'http' => ['timeout' => 20, 'header' => "Cache-Control: no-cache\r\n"],
]));
if ($live === false) {
    echo "FAIL fetch\n";
} else {
    echo 'live_has_onclick=' . (stripos($live, 'onclick=') !== false && stripos($live, '/profile') !== false ? 'yes' : 'no') . "\n";
    echo 'live_has_go_profile=' . (stripos($live, 'data-ratib-go-profile') !== false ? 'yes' : 'no') . "\n";
    echo 'live_has_v13_marker=' . (stripos($live, 'ratib-profile-nav=v13') !== false ? 'yes' : 'no') . "\n";
    if (preg_match('/class="ratib-nav__brand-profile"[^>]*>/', $live, $m)) {
        echo 'live_brand_profile_tag=' . $m[0] . "\n";
    }
}

echo "\nIf disk=yes but live=no → purge LiteSpeed Cache in cPanel (Cache Manager → Purge All).\n";
