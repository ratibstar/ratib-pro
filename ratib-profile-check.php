<?php
declare(strict_types=1);

/**
 * Upload to site document root (same folder as designed-status.php).
 * Open: https://out.ratib.sa/ratib-profile-check.php
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$root = __DIR__;
$doc = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

function ratib_has(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

echo "ratib-profile-check\n";
echo 'php_version=' . PHP_VERSION . "\n";
echo 'allow_url_fopen=' . (ini_get('allow_url_fopen') ? 'yes' : 'no') . "\n";
echo 'curl=' . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
echo 'HTTP_HOST=' . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo 'DOCUMENT_ROOT=' . $doc . "\n";
echo 'This script dir=' . $root . "\n";
$rootReal = @realpath($root);
$docReal = $doc !== '' ? @realpath($doc) : false;
echo 'dirs_match=' . ($rootReal && $docReal && $rootReal === $docReal ? 'yes' : 'NO — upload to public_html root') . "\n\n";

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
    if (ratib_has($sample, 'ratib-deploy-probe-via-home')) {
        $flags[] = 'deploy-probe';
    }
    $flagStr = $flags !== [] ? ' (' . implode(', ', $flags) . ')' : ' (OLD — no profile markers)';
    echo '[OK] ' . $rel . ' mtime=' . date('c', (int) filemtime($path)) . ' bytes=' . filesize($path) . $flagStr . "\n";
}

$build = $root . '/public/ratib-build.txt';
echo "\nbuild_marker=" . (is_file($build) ? trim((string) file_get_contents($build)) : 'missing') . "\n";

$repoCandidates = [
    '/home/outratib/repositories/ratib-pro',
    '/home/outratib/repositories/ratibprogram',
    dirname($root) . '/repositories/ratib-pro',
];
echo "\n--- Git checkout on server (for copy sync) ---\n";
foreach ($repoCandidates as $c) {
    $real = @realpath($c);
    $chrome = $real ? $real . '/includes/ratib-home-public-chrome-top.php' : '';
    $hasProfile = is_file($chrome) && ratib_has((string) @file_get_contents($chrome, false, null, 0, 12000), 'ratib-nav__brand-profile');
    echo ($real && is_dir($real) ? '[FOUND] ' : '[missing] ') . $c;
    if ($real) {
        echo ' -> ' . $real . ' profile_chrome=' . ($hasProfile ? 'yes' : 'no');
    }
    echo "\n";
}

echo "\n--- GitHub fetch test ---\n";
$testUrl = 'https://raw.githubusercontent.com/ratibstar/ratib-pro/main/public/ratib-build.txt';
$testBody = false;
if (function_exists('curl_init')) {
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'RatibProfileCheck/1.0',
    ]);
    $testBody = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo 'curl_http=' . $code . ' bytes=' . (is_string($testBody) ? strlen($testBody) : 0);
    if ($err !== '') {
        echo ' err=' . $err;
    }
    echo "\n";
} elseif (ini_get('allow_url_fopen')) {
    $testBody = @file_get_contents($testUrl);
    echo 'fopen_bytes=' . (is_string($testBody) ? strlen($testBody) : 0) . "\n";
} else {
    echo "github_blocked=allow_url_fopen off and no curl\n";
}

echo "\nNext: upload pages/ratib-copy-from-repo.php and open ?run=1&key=ratib-deploy-sync-2026\n";
