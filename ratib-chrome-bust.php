<?php
/**
 * Chrome / cache diagnostic (root — always reachable).
 * https://out.ratib.sa/ratib-chrome-bust.php
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$root = __DIR__;
$chrome = $root . '/includes/ratib-home-public-chrome-top.php';
$sync = $root . '/includes/ratib-home-public-nav-sync.php';
$home = $root . '/pages/home.php';

echo "ratib-chrome-bust\n";
echo 'time=' . gmdate('c') . "\n\n";

foreach (['chrome' => $chrome, 'nav-sync' => $sync, 'home' => $home] as $label => $path) {
    echo "=== {$label} ===\n";
    if (!is_file($path)) {
        echo "MISSING {$path}\n\n";
        continue;
    }
    echo 'mtime=' . date('c', (int) filemtime($path)) . "\n";
    echo 'bytes=' . filesize($path) . "\n";
    $body = (string) file_get_contents($path);
    echo 'has_onclick=' . (stripos($body, 'onclick=') !== false ? 'yes' : 'no') . "\n";
    echo 'has_go_profile=' . (stripos($body, 'data-ratib-go-profile') !== false ? 'yes' : 'no') . "\n";
    echo 'has_v13=' . (stripos($body, 'v13-onclick') !== false ? 'yes' : 'no') . "\n";
    echo 'has_wireProfileLink=' . (stripos($body, 'wireProfileLink') !== false ? 'yes' : 'no') . "\n\n";
}

if (function_exists('opcache_invalidate')) {
    foreach ([$chrome, $sync, $home] as $p) {
        if (is_file($p)) {
            opcache_invalidate($p, true);
        }
    }
    echo "opcache_invalidate=done\n\n";
}

$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'out.ratib.sa';
$liveUrl = 'https://' . $host . '/pages/home.php';
$live = function_exists('curl_init') ? null : @file_get_contents($liveUrl);
if (function_exists('curl_init')) {
    $ch = curl_init($liveUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache'],
    ]);
    $live = curl_exec($ch);
    curl_close($ch);
}
echo "=== Live HTML (home.php) ===\n";
if (!is_string($live) || $live === '') {
    echo "FAIL fetch\n";
} else {
    echo 'live_v13=' . (stripos($live, 'ratib-profile-nav=v13') !== false ? 'yes' : 'no') . "\n";
    echo 'live_onclick_profile=' . (stripos($live, 'onclick=') !== false && stripos($live, '/profile') !== false ? 'yes' : 'no') . "\n";
    echo 'live_go_profile=' . (stripos($live, 'data-ratib-go-profile') !== false ? 'yes' : 'no') . "\n";
    if (preg_match('/class="ratib-nav__brand-profile"[^>]*>/', $live, $m)) {
        echo 'brand_tag=' . trim($m[0]) . "\n";
    }
}

echo "\n--- What to do ---\n";
echo "disk has_onclick=yes AND live_v13=no → cPanel LiteSpeed: Purge All cache, then Ctrl+Shift+R\n";
echo "disk has_onclick=no → re-upload includes/ratib-home-public-chrome-top.php\n";
echo "Profile page OK at: https://{$host}/profile\n";
