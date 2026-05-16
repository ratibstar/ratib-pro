<?php
declare(strict_types=1);

/**
 * ONE-TIME emergency sync when cPanel auto-deploy does not update the live docroot.
 *
 * 1) cPanel → File Manager → open the folder that already contains pages/home.php
 * 2) Upload this file to pages/ratib-sync-from-github.php
 * 3) Visit: /pages/ratib-sync-from-github.php?run=1&key=ratib-deploy-sync-2026
 * 4) DELETE this file after success.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$key = (string) ($_GET['key'] ?? '');
if (((string) ($_GET['run'] ?? '')) !== '1' || !hash_equals('ratib-deploy-sync-2026', $key)) {
    http_response_code(403);
    echo "Forbidden. Use: ?run=1&key=ratib-deploy-sync-2026\n";
    exit;
}

$root = dirname(__DIR__);
$branch = 'main';
$repo = 'ratibstar/ratib-pro';
$rawBase = "https://raw.githubusercontent.com/{$repo}/{$branch}/";

$files = [
    'public/ratib-build.txt',
    'pages/home.php',
    'pages/about.php',
    'pages/deploy-root.php',
    'includes/ratib-home-public-chrome-top.php',
    'includes/ratib-home-public-nav-sync.php',
    'includes/ratib-home-public-nav-bootstrap.php',
    'includes/site-content-home-data.php',
    'includes/ratib-about-profile-data.php',
    'includes/ratib-about-sections.php',
    'includes/ratib-mega-nav-config.php',
    'includes/ratib-mega-nav-resolve.php',
    'includes/ratib-mega-nav-render.php',
    'js/pages/home-page.js',
    'css/pages/home-public.css',
    'css/pages/about-enterprise.css',
    'js/pages/about-enterprise.js',
    '.htaccess',
];

echo "ratib-sync-from-github\n";
echo 'probe_root=' . $root . "\n";
echo 'document_root=' . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo 'branch=' . $branch . "\n\n";

$ok = 0;
$fail = 0;
foreach ($files as $rel) {
    $rel = str_replace('\\', '/', $rel);
    $url = $rawBase . $rel;
    $dest = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        echo "FAIL mkdir {$dir}\n";
        $fail++;
        continue;
    }
    $ctx = stream_context_create([
        'http' => ['timeout' => 60, 'header' => "User-Agent: RatibEmergencySync/1.0\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if (!is_string($body) || $body === '' || str_starts_with($body, '404:')) {
        echo "FAIL fetch {$rel}\n";
        $fail++;
        continue;
    }
    if (@file_put_contents($dest, $body) === false) {
        echo "FAIL write {$dest}\n";
        $fail++;
        continue;
    }
    $about = ($rel === 'pages/about.php') ? ' [about]' : '';
    echo "OK {$rel} bytes=" . strlen($body) . $about . "\n";
    $ok++;
}

echo "\nSummary: ok={$ok} fail={$fail}\n";
echo 'about_exists=' . (is_file($root . '/pages/about.php') ? 'yes' : 'no') . "\n";
echo 'chrome_profile=' . (is_file($root . '/includes/ratib-home-public-chrome-top.php')
    && str_contains((string) file_get_contents($root . '/includes/ratib-home-public-chrome-top.php'), 'ratib-nav__brand-profile') ? 'yes' : 'no') . "\n";
echo "\nNext: open /pages/home.php?ratib_deploy_probe=1 then DELETE this sync script.\n";
