<?php
require_once __DIR__ . '/includes/ratib-php74-compat.php';

/**
 * Site diagnostic + one-click copy Profile files from git repo → public_html.
 *
 * Upload to document root (same folder as .htaccess).
 *   Check:  https://out.ratib.sa/designed-status.php
 *   Deploy: https://out.ratib.sa/designed-status.php?copy=1&key=ratib-deploy-sync-2026
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$here = __DIR__;
$doc = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/') : '';
$host = $_SERVER['HTTP_HOST'] ?? '(unknown)';

function ratib_has(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

function ratib_find_repo_source(string $publicHtml): ?string
{
    $candidates = [
        '/home/outratib/repositories/ratib-pro',
        '/home/outratib/repositories/ratibprogram',
        dirname($publicHtml) . '/repositories/ratib-pro',
        dirname($publicHtml) . '/ratib-pro',
    ];
    $fallback = null;
    foreach ($candidates as $c) {
        $real = @realpath($c);
        if (!$real || !is_dir($real) || !is_file($real . '/pages/home.php')) {
            continue;
        }
        $chrome = $real . '/includes/ratib-home-public-chrome-top.php';
        if (is_file($chrome) && ratib_has((string) @file_get_contents($chrome, false, null, 0, 12000), 'ratib-nav__brand-profile')) {
            return $real;
        }
        $fallback = $fallback ?? $real;
    }

    return $fallback;
}

$copyRun = isset($_GET['copy']) && (string) $_GET['copy'] === '1';
$copyKey = (string) ($_GET['key'] ?? '');

if ($copyRun) {
    if (!hash_equals('ratib-deploy-sync-2026', $copyKey)) {
        http_response_code(403);
        echo "Forbidden. Use: ?copy=1&key=ratib-deploy-sync-2026\n";
        exit;
    }

    $files = [
        'includes/ratib-php74-compat.php',
        'public/ratib-build.txt',
        'pages/home.php',
        'pages/about.php',
        'pages/company-profile.php',
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
        'js/pages/ratib-mega-nav.js',
        'css/pages/home-public.css',
        'css/pages/about-enterprise.css',
        'js/pages/about-enterprise.js',
    ];

    $source = ratib_find_repo_source($here);
    echo "=== RATIB deploy copy (public_html) ===\n\n";
    echo "dest={$here}\n";
    echo 'document_root=' . $doc . "\n";
    echo 'source_repo=' . ($source ?? 'NOT FOUND') . "\n\n";

    if ($source === null) {
        echo "No git checkout found with Profile code.\n";
        echo "cPanel → Git Version Control → Update from Remote → Deploy HEAD Commit, then run this URL again.\n";
        exit(1);
    }

    $ok = 0;
    $fail = 0;
    foreach ($files as $rel) {
        $rel = str_replace('\\', '/', $rel);
        $src = $source . '/' . $rel;
        $dest = $here . '/' . $rel;
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

    $chromeSample = is_file($here . '/includes/ratib-home-public-chrome-top.php')
        ? (string) @file_get_contents($here . '/includes/ratib-home-public-chrome-top.php', false, null, 0, 16000)
        : '';

    echo "\nSummary: ok={$ok} fail={$fail}\n";
    echo 'company_profile_php=' . (is_file($here . '/pages/company-profile.php') ? 'yes' : 'no') . "\n";
    echo 'chrome_brand_profile=' . (ratib_has($chromeSample, 'ratib-nav__brand-profile') ? 'yes' : 'no') . "\n";
    echo 'chrome_primary_links_8=' . (ratib_has($chromeSample, 'primary-links=8') ? 'yes' : 'no') . "\n";
    echo "\nHard-refresh: https://{$host}/pages/home.php\n";
    exit;
}

echo "=== RATIB site diagnostic ===\n\n";
echo 'php_version=' . PHP_VERSION . " (7.4+ supported)\n";
echo "HTTP_HOST: {$host}\n";
echo 'DOCUMENT_ROOT: ' . ($doc !== '' ? $doc : '(empty)') . "\n";
echo "This file (__DIR__): {$here}\n";
$rootReal = @realpath($here);
$docReal = $doc !== '' ? @realpath($doc) : false;
echo 'dirs_match=' . ($rootReal && $docReal && $rootReal === $docReal ? 'yes' : 'NO') . "\n\n";

echo "--- Profile files on live site ---\n";
$profileFiles = [
    'pages/home.php',
    'pages/about.php',
    'pages/company-profile.php',
    'includes/ratib-home-public-chrome-top.php',
    'includes/ratib-home-public-nav-sync.php',
    'js/pages/ratib-mega-nav.js',
    'public/ratib-build.txt',
];
foreach ($profileFiles as $rel) {
    $path = $here . '/' . $rel;
    if (!is_file($path)) {
        echo "[MISSING] {$rel}\n";
        continue;
    }
    $sample = (string) @file_get_contents($path, false, null, 0, 12000);
    $flags = [];
    if (ratib_has($sample, 'primary-links=8')) {
        $flags[] = 'primary-links=8';
    }
    if (ratib_has($sample, 'ratib-nav__brand-profile')) {
        $flags[] = 'brand-profile';
    }
    if (ratib_has($sample, "linkByKey['about']")) {
        $flags[] = 'nav-inject';
    }
    if (ratib_has($sample, 'ratibProfileNavPatch')) {
        $flags[] = 'mega-nav-patch';
    }
    $flagStr = $flags !== [] ? ' (' . implode(', ', $flags) . ')' : ' (OLD)';
    echo '[OK] ' . $rel . $flagStr . "\n";
}

echo "\n--- Git checkout on server ---\n";
$source = ratib_find_repo_source($here);
echo 'best_source=' . ($source ?? 'NOT FOUND') . "\n";
foreach (
    [
        '/home/outratib/repositories/ratib-pro',
        '/home/outratib/repositories/ratibprogram',
        dirname($here) . '/repositories/ratib-pro',
    ] as $c
) {
    $real = @realpath($c);
    $profile = false;
    if ($real && is_file($real . '/includes/ratib-home-public-chrome-top.php')) {
        $profile = ratib_has(
            (string) @file_get_contents($real . '/includes/ratib-home-public-chrome-top.php', false, null, 0, 12000),
            'ratib-nav__brand-profile'
        );
    }
    echo ($real ? '[FOUND] ' : '[missing] ') . $c;
    if ($real) {
        echo ' profile=' . ($profile ? 'yes' : 'no');
    }
    echo "\n";
}

echo "\n=== Deploy Profile to live site ===\n";
echo "1) cPanel → Git → Update from Remote → Deploy HEAD Commit\n";
echo "2) Open:\n";
echo "   https://{$host}/designed-status.php?copy=1&key=ratib-deploy-sync-2026\n";
echo "3) Hard-refresh /pages/home.php (Ctrl+Shift+R)\n\n";

echo "Note: /pages/ratib-copy-from-repo.php shows 'Not found' until the .php file exists on disk.\n";
echo "Use this designed-status.php URL instead (root file, always works).\n";
