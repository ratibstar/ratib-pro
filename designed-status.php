<?php
require_once __DIR__ . '/includes/ratib-php74-compat.php';

/**
 * Site diagnostic + one-click copy Profile files from git repo → public_html.
 *
 * Upload to document root (same folder as .htaccess).
 *   Check:  https://out.ratib.sa/designed-status.php
 *   Deploy: https://out.ratib.sa/designed-status.php?copy=1&key=ratib-deploy-sync-2026
 *   (Uses local git repo if present, else GitHub raw via curl — works on PHP 7.4)
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$here = __DIR__;
$doc = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/') : '';
$host = $_SERVER['HTTP_HOST'] ?? '(unknown)';

$ratibDeployFiles = [
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

function ratib_has($haystack, $needle)
{
    return $needle === '' || strpos((string) $haystack, (string) $needle) !== false;
}

/**
 * @return string|false
 */
function ratib_http_get($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'RatibDeploy/2.1-php74',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code === 200 && is_string($body) && $body !== '') {
            return $body;
        }
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => ['timeout' => 120, 'header' => "User-Agent: RatibDeploy/2.1-php74\r\n"],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        return (is_string($body) && $body !== '') ? $body : false;
    }

    return false;
}

function ratib_deploy_summary($here)
{
    $chromeSample = is_file($here . '/includes/ratib-home-public-chrome-top.php')
        ? (string) @file_get_contents($here . '/includes/ratib-home-public-chrome-top.php', false, null, 0, 16000)
        : '';

    echo 'company_profile_php=' . (is_file($here . '/pages/company-profile.php') ? 'yes' : 'no') . "\n";
    echo 'chrome_brand_profile=' . (ratib_has($chromeSample, 'ratib-nav__brand-profile') ? 'yes' : 'no') . "\n";
    echo 'chrome_primary_links_8=' . (ratib_has($chromeSample, 'primary-links=8') ? 'yes' : 'no') . "\n";
}

function ratib_find_repo_source($publicHtml)
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

    global $ratibDeployFiles;

    $source = ratib_find_repo_source($here);
    echo "=== RATIB deploy (public_html) ===\n\n";
    echo 'php_version=' . PHP_VERSION . "\n";
    echo 'curl=' . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
    echo "dest={$here}\n";
    echo 'document_root=' . $doc . "\n";
    echo 'source_repo=' . ($source !== null ? $source : 'NOT FOUND') . "\n\n";

    $ok = 0;
    $fail = 0;
    $mode = 'repo';

    if ($source === null) {
        $mode = 'github-curl';
        echo "No git folder on server — downloading from GitHub (main branch)...\n\n";
        $rawBase = 'https://raw.githubusercontent.com/ratibstar/ratib-pro/main/';
        foreach ($ratibDeployFiles as $rel) {
            $rel = str_replace('\\', '/', $rel);
            $url = $rawBase . $rel;
            $dest = $here . '/' . $rel;
            $dir = dirname($dest);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                echo "FAIL mkdir {$dir}\n";
                $fail++;
                continue;
            }
            $body = ratib_http_get($url);
            if ($body === false || ratib_has($body, '404: Not Found')) {
                echo "FAIL fetch {$rel}\n";
                $fail++;
                continue;
            }
            if (@file_put_contents($dest, $body) === false) {
                echo "FAIL write {$rel}\n";
                $fail++;
                continue;
            }
            echo 'OK ' . $rel . ' bytes=' . strlen($body) . " (github)\n";
            $ok++;
        }
    } else {
        foreach ($ratibDeployFiles as $rel) {
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
            echo 'OK ' . $rel . ' bytes=' . filesize($dest) . " (repo)\n";
            $ok++;
        }
    }

    echo "\nSummary: mode={$mode} ok={$ok} fail={$fail}\n";
    ratib_deploy_summary($here);
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

echo "\n=== Deploy Profile to live site (PHP 7.4) ===\n";
echo "Your check: no git repo on server — deploy uses GitHub via curl.\n";
echo "Open:\n";
echo "   https://{$host}/designed-status.php?copy=1&key=ratib-deploy-sync-2026\n";
echo "Then hard-refresh /pages/home.php (Ctrl+Shift+R)\n\n";

echo "Note: /pages/ratib-copy-from-repo.php shows 'Not found' until the .php file exists on disk.\n";
echo "Use this designed-status.php URL instead (root file, always works).\n";
