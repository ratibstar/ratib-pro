<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/designed-launcher.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/designed-launcher.php`.
 */

/**
 * Designed storefront — no includes. HTML redirect only (no Location header; some hosts HTTP 500 on 302 from /pages/).
 * ?ping=1 → plain text diagnostic.
 */
if (isset($_GET['ping']) && (string) $_GET['ping'] === '1') {
    @header('Content-Type: text/plain; charset=UTF-8');
    echo 'designed-launcher OK, PHP ' . PHP_VERSION . "\n";
    exit;
}

$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/pages/designed-launcher.php'));
if (!preg_match('#^(.*)/pages/designed-launcher\.php$#i', $script, $m)) {
    $prefix = '';
} else {
    $prefix = $m[1];
}

$targetPath = $prefix . '/public/index.php';
$q = $_GET;
$q['ratib_designed'] = '1';
$query = http_build_query($q);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
$scheme = $https ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');

if ($host === '') {
    @header('Content-Type: text/plain; charset=UTF-8');
    echo "HTTP_HOST missing. Open manually: /public/index.php?ratib_designed=1\n";
    exit;
}

$absolute = $scheme . '://' . $host . $targetPath . ($query !== '' ? '?' . $query : '');
$u = htmlspecialchars($absolute, ENT_QUOTES, 'UTF-8');
$uJson = json_encode($absolute, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if ($uJson === false) {
    $uJson = '""';
}

@header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Designed</title>';
echo '<meta http-equiv="refresh" content="0;url=' . $u . '">';
echo '</head><body>';
echo '<script>location.replace(' . $uJson . ');</script>';
echo '<p>Redirecting… If nothing happens, <a href="' . $u . '">open Designed</a>.</p>';
echo '</body></html>';
