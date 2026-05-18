<?php
/**
 * ONE file: check + deploy Profile to public_html (PHP 7.4, curl only).
 *
 * Check:  https://out.ratib.sa/ratib-profile-check.php
 * Deploy: https://out.ratib.sa/ratib-profile-check.php?deploy=1&key=ratib-deploy-sync-2026
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$compat = __DIR__ . '/includes/ratib-php74-compat.php';
if (is_file($compat)) {
    require_once $compat;
}

$root = __DIR__;
$doc = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'out.ratib.sa';

function ratib_has($haystack, $needle)
{
    return $needle === '' || strpos((string) $haystack, (string) $needle) !== false;
}

/**
 * @return string|false
 */
function ratib_http_get($url)
{
    if (!function_exists('curl_init')) {
        return false;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => 'RatibProfileDeploy/3.0-php74',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && is_string($body) && $body !== '' && !ratib_has($body, '404: Not Found')) {
        return $body;
    }

    return false;
}

$deployFiles = [
    'includes/ratib-php74-compat.php',
    'includes/ratib_html_global_ai_patch.php',
    'control-panel/includes/config.php',
    'control-panel/cp-ping.php',
    'public/ratib-build.txt',
    'pages/home.php',
    'pages/about.php',
    'pages/company-profile.php',
    'includes/ratib-profile-nav-guard.php',
    'includes/ratib-home-public-chrome-top.php',
    'includes/ratib-home-public-nav-sync.php',
    'includes/ratib-home-public-nav-bootstrap.php',
    'includes/ratib-about-profile-data.php',
    'includes/ratib-about-sections.php',
    'js/pages/ratib-mega-nav.js',
    'js/pages/home-page.js',
    'css/pages/home-public.css',
    'css/pages/about-enterprise.css',
    'js/pages/about-enterprise.js',
];

$deployRun = isset($_GET['deploy']) && (string) $_GET['deploy'] === '1';
$deployKey = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($deployRun) {
    if (!hash_equals('ratib-deploy-sync-2026', $deployKey)) {
        http_response_code(403);
        echo "Forbidden. Use: ?deploy=1&key=ratib-deploy-sync-2026\n";
        exit;
    }

    echo "=== RATIB Profile deploy (GitHub → public_html) ===\n\n";
    echo 'php_version=' . PHP_VERSION . "\n";
    echo 'curl=' . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
    echo "dest={$root}\n\n";

    if (!function_exists('curl_init')) {
        echo "FAIL: curl required on this server.\n";
        exit(1);
    }

    $rawBase = 'https://raw.githubusercontent.com/ratibstar/ratib-pro/main/';
    $ok = 0;
    $fail = 0;

    foreach ($deployFiles as $rel) {
        $rel = str_replace('\\', '/', $rel);
        $url = $rawBase . $rel;
        $dest = $root . '/' . $rel;
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            echo "FAIL mkdir {$dir}\n";
            $fail++;
            continue;
        }
        $body = ratib_http_get($url);
        if ($body === false) {
            echo "FAIL fetch {$rel}\n";
            $fail++;
            continue;
        }
        if (@file_put_contents($dest, $body) === false) {
            echo "FAIL write {$rel}\n";
            $fail++;
            continue;
        }
        echo 'OK ' . $rel . ' bytes=' . strlen($body) . "\n";
        $ok++;
    }

    $chrome = is_file($root . '/includes/ratib-home-public-chrome-top.php')
        ? (string) @file_get_contents($root . '/includes/ratib-home-public-chrome-top.php', false, null, 0, 16000)
        : '';

    echo "\nSummary: ok={$ok} fail={$fail}\n";
    echo 'company_profile_php=' . (is_file($root . '/pages/company-profile.php') ? 'yes' : 'no') . "\n";
    echo 'chrome_brand_profile=' . (ratib_has($chrome, 'ratib-nav__brand-profile') ? 'yes' : 'no') . "\n";
    echo 'chrome_primary_links_8=' . (ratib_has($chrome, 'primary-links=8') ? 'yes' : 'no') . "\n";
    echo "\nDone. Hard-refresh: https://{$host}/pages/home.php\n";
    echo "Re-run check: https://{$host}/ratib-profile-check.php\n";
    exit;
}

echo "ratib-profile-check v3\n";
echo 'php_version=' . PHP_VERSION . "\n";
echo 'allow_url_fopen=' . (ini_get('allow_url_fopen') ? 'yes' : 'no') . "\n";
echo 'curl=' . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
echo 'HTTP_HOST=' . $host . "\n";
echo 'DOCUMENT_ROOT=' . $doc . "\n";
echo 'This script dir=' . $root . "\n";
$rootReal = @realpath($root);
$docReal = $doc !== '' ? @realpath($doc) : false;
echo 'dirs_match=' . ($rootReal && $docReal && $rootReal === $docReal ? 'yes' : 'NO') . "\n\n";

$checks = [
    'pages/home.php',
    'pages/about.php',
    'pages/company-profile.php',
    'includes/ratib-home-public-chrome-top.php',
    'includes/ratib-home-public-nav-sync.php',
    'js/pages/ratib-mega-nav.js',
    'public/ratib-build.txt',
];

foreach ($checks as $rel) {
    $path = $root . '/' . $rel;
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
    echo '[OK] ' . $rel . ' mtime=' . date('c', (int) filemtime($path)) . ' bytes=' . filesize($path) . $flagStr . "\n";
}

$build = $root . '/public/ratib-build.txt';
echo "\nbuild_marker=" . (is_file($build) ? trim((string) file_get_contents($build)) : 'missing') . "\n";

echo "\n>>> Permission check (home 711, dirs 755, files 644):\n";
echo "https://{$host}/pages/ratib-perms-check.php\n";
echo "Auto-fix: https://{$host}/pages/ratib-perms-check.php?fix=1&key=ratib-deploy-sync-2026\n";
echo "\n>>> Legacy fix script:\n";
echo "https://{$host}/pages/ratib-fix-perms.php?run=1&key=ratib-deploy-sync-2026\n";
echo "\n>>> Then deploy Profile:\n";
echo "https://{$host}/pages/ratib-profile-deploy.php?deploy=1&key=ratib-deploy-sync-2026\n";
