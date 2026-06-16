<?php
require_once dirname(__DIR__) . '/includes/rateb-php74-compat.php';

/**
 * Copy Profile files from cPanel git checkout → public_html (no GitHub download).
 * PHP 7.4+ supported.
 *
 * 1) Upload to /home/admin/public_html/pages/rateb-copy-from-repo.php
 * 2) Visit: /pages/rateb-copy-from-repo.php?run=1&key=rateb-deploy-sync-2026
 * 3) DELETE this file after success.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$key = (string) ($_GET['key'] ?? '');
if (((string) ($_GET['run'] ?? '')) !== '1' || !hash_equals('rateb-deploy-sync-2026', $key)) {
    http_response_code(403);
    echo "Forbidden. Use: ?run=1&key=rateb-deploy-sync-2026\n";
    exit;
}

$root = dirname(__DIR__);

function rateb_has(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

$repoCandidates = [
    '/home/admin/repositories/rateb-pro',
    '/home/admin/repositories/ratebprogram',
    dirname($root) . '/repositories/rateb-pro',
    dirname($root) . '/rateb-pro',
];

$source = null;
foreach ($repoCandidates as $c) {
    $real = @realpath($c);
    if (!$real || !is_dir($real)) {
        continue;
    }
    $chrome = $real . '/includes/rateb-home-public-chrome-top.php';
    if (is_file($chrome) && rateb_has((string) @file_get_contents($chrome, false, null, 0, 12000), 'rateb-nav__brand-profile')) {
        $source = $real;
        break;
    }
    if (is_file($real . '/pages/home.php')) {
        $source = $source ?? $real;
    }
}

$files = [
    'includes/rateb-php74-compat.php',
    'includes/rateb_html_global_ai_patch.php',
    'control-panel/includes/config.php',
    'control-panel/cp-ping.php',
    'public/rateb-build.txt',
    'pages/home.php',
    'pages/about.php',
    'pages/company-profile.php',
    'pages/deploy-root.php',
    'includes/rateb-home-public-chrome-top.php',
    'includes/rateb-home-public-nav-sync.php',
    'includes/rateb-home-public-nav-bootstrap.php',
    'includes/site-content-home-data.php',
    'includes/rateb-about-profile-data.php',
    'includes/rateb-about-sections.php',
    'includes/rateb-mega-nav-config.php',
    'includes/rateb-mega-nav-resolve.php',
    'includes/rateb-mega-nav-render.php',
    'js/pages/home-page.js',
    'js/pages/rateb-mega-nav.js',
    'css/pages/home-public.css',
    'css/pages/about-enterprise.css',
    'js/pages/about-enterprise.js',
];

echo "rateb-copy-from-repo v1\n";
echo 'dest_root=' . $root . "\n";
echo 'document_root=' . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo 'source_repo=' . ($source ?? 'NOT FOUND') . "\n\n";

if ($source === null) {
    echo "No git checkout with Profile code found.\n";
    echo "Upload files manually to public_html OR fix GitHub fetch (see rateb-profile-check.php).\n";
    exit(1);
}

$ok = 0;
$fail = 0;
foreach ($files as $rel) {
    $rel = str_replace('\\', '/', $rel);
    $src = $source . '/' . $rel;
    $dest = $root . '/' . $rel;
    if (!is_file($src)) {
        echo "SKIP missing in repo {$rel}\n";
        continue;
    }
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        echo "FAIL mkdir {$dir}\n";
        $fail++;
        continue;
    }
    if (!@copy($src, $dest)) {
        echo "FAIL copy {$rel}\n";
        $fail++;
        continue;
    }
    echo 'OK ' . $rel . ' bytes=' . filesize($dest) . "\n";
    $ok++;
}

$chromeSample = is_file($root . '/includes/rateb-home-public-chrome-top.php')
    ? (string) @file_get_contents($root . '/includes/rateb-home-public-chrome-top.php', false, null, 0, 16000)
    : '';

echo "\nSummary: ok={$ok} fail={$fail}\n";
echo 'company_profile_php=' . (is_file($root . '/pages/company-profile.php') ? 'yes' : 'no') . "\n";
echo 'chrome_brand_profile=' . (rateb_has($chromeSample, 'rateb-nav__brand-profile') ? 'yes' : 'no') . "\n";
echo 'chrome_primary_links_8=' . (rateb_has($chromeSample, 'primary-links=8') ? 'yes' : 'no') . "\n";
echo "\nHard-refresh /pages/home.php then DELETE this script.\n";
