<?php
/**
 * ONE file: check + deploy Profile to public_html (PHP 7.4, curl only).
 *
 * Check:  https://rateb.sa/ratib-profile-check.php
 * Deploy: https://rateb.sa/ratib-profile-check.php?deploy=1&key=ratib-deploy-sync-2026
 * Infra (23): https://rateb.sa/ratib-profile-check.php?infra23=1&key=ratib-deploy-sync-2026
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$compat = __DIR__ . '/includes/ratib-php74-compat.php';
if (is_file($compat)) {
    require_once $compat;
}

$root = __DIR__;
$doc = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'rateb.sa';

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
    'includes/ratib-public-base-url.php',
    'includes/ratib-home-public-chrome-top.php',
    'includes/ratib-site-content-rebrand-sanitize.php',
    'includes/site-content-home-data.php',
    'includes/ratib-home-public-nav-sync.php',
    'includes/ratib-home-public-nav-bootstrap.php',
    'includes/ratib-about-profile-data.php',
    'includes/ratib-about-sections.php',
    'js/pages/ratib-profile-nav-guard.js',
    'js/pages/ratib-mega-nav.js',
    'js/pages/home-page.js',
    'css/pages/home-public.css',
    'css/pages/about-enterprise.css',
    'js/pages/about-enterprise.js',
    '.htaccess',
    'ratib-chrome-bust.php',
    'pages/ratib-chrome-bust.php',
    'ratib-profile-fix.php',
    'ratib-profile-check.php',
    'pages/ratib-cms-rebrand-apply.php',
];

/** @var list<string> Infrastructure marketplace bundle (scripts/infra-deploy-23-files.list) */
$infraDeployFiles = [
    'modules/infrastructure-marketplace/Infrastructure/InfraEnvBootstrap.php',
    'modules/infrastructure-marketplace/Security/Secrets/InfraProviderSecretsSync.php',
    'modules/infrastructure-marketplace/Cli/infra-ensure-secret-key.php',
    'modules/infrastructure-marketplace/Config/RuntimeOverrideStore.php',
    'modules/infrastructure-marketplace/Config/ModuleConfig.php',
    'modules/infrastructure-marketplace/bootstrap.php',
    'api/infrastructure-marketplace/control-update.php',
    'storage/infrastructure-marketplace/.htaccess',
    'modules/infrastructure-marketplace/Infrastructure/SchemaHelpers.php',
    'modules/infrastructure-marketplace/Migrations/005_provider_activation_marketplace.sql',
    'modules/infrastructure-marketplace/Migrations/008_provider_secrets_and_events.sql',
    'modules/infrastructure-marketplace/Security/Secrets/ProviderSecretCipher.php',
    'modules/infrastructure-marketplace/Security/Secrets/ProviderSecretStore.php',
    'modules/infrastructure-marketplace/Security/Secrets/ProviderSecretDbProvider.php',
    'modules/infrastructure-marketplace/Observability/ProviderEventLogger.php',
    'modules/infrastructure-marketplace/Observability/ProviderEventBus.php',
    'modules/infrastructure-marketplace/Providers/Health/ProviderHealthMonitor.php',
    'modules/infrastructure-marketplace/Cli/provider-health-monitor.php',
    'modules/infrastructure-marketplace/Cli/provider-events-retention.php',
    'modules/infrastructure-marketplace/Cli/production-verify.php',
    'modules/infrastructure-marketplace/Observability/ProviderFailureThrottle.php',
    'modules/infrastructure-marketplace/Observability/ProviderEventsRetention.php',
    'scripts/run-infra-migrations-safe.sh',
];

$deployRun = isset($_GET['deploy']) && (string) $_GET['deploy'] === '1';
$infra23Run = isset($_GET['infra23']) && (string) $_GET['infra23'] === '1';
$deployKey = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($deployRun || $infra23Run) {
    if (!hash_equals('ratib-deploy-sync-2026', $deployKey)) {
        http_response_code(403);
        echo "Forbidden. Use: ?deploy=1&key=ratib-deploy-sync-2026 or ?infra23=1&key=ratib-deploy-sync-2026\n";
        exit;
    }

    $batch = $infra23Run ? $infraDeployFiles : $deployFiles;
    $label = $infra23Run ? 'Infra marketplace (23 files)' : 'Profile';

    echo "=== RATIB {$label} deploy (GitHub → public_html) ===\n\n";
    echo 'php_version=' . PHP_VERSION . "\n";
    echo 'curl=' . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
    echo "dest={$root}\n";
    echo 'files=' . count($batch) . "\n\n";

    if (!function_exists('curl_init')) {
        echo "FAIL: curl required on this server.\n";
        exit(1);
    }

    $rawBase = 'https://raw.githubusercontent.com/ratibstar/ratib-pro/main/';
    $ok = 0;
    $fail = 0;

    foreach ($batch as $rel) {
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
            $w = is_writable($dest) ? 'writable' : (is_writable(dirname($dest)) ? 'dir-writable' : 'not-writable');
            $own = function_exists('fileowner') ? (string) @fileowner($dest) : '?';
            $mod = function_exists('fileperms') ? sprintf('%04o', @fileperms($dest) & 07777) : '?';
            echo "FAIL write {$rel} ({$w} owner_uid={$own} mode={$mod})\n";
            $fail++;
            continue;
        }
        @chmod($dest, 0644);
        echo 'OK ' . $rel . ' bytes=' . strlen($body) . "\n";
        $ok++;
    }

    echo "\nSummary: ok={$ok} fail={$fail}\n";
    if (!$infra23Run) {
        $chrome = is_file($root . '/includes/ratib-home-public-chrome-top.php')
            ? (string) @file_get_contents($root . '/includes/ratib-home-public-chrome-top.php', false, null, 0, 16000)
            : '';
        echo 'company_profile_php=' . (is_file($root . '/pages/company-profile.php') ? 'yes' : 'no') . "\n";
        echo 'chrome_brand_profile=' . (ratib_has($chrome, 'ratib-nav__brand-profile') ? 'yes' : 'no') . "\n";
        echo 'chrome_primary_links_8=' . (ratib_has($chrome, 'primary-links=8') ? 'yes' : 'no') . "\n";
        echo "\nDone. Hard-refresh: https://{$host}/pages/home.php\n";
    } else {
        $verify = $root . '/modules/infrastructure-marketplace/Cli/production-verify.php';
        echo 'production_verify=' . (is_file($verify) ? 'yes' : 'no') . "\n";
        echo "\nDone. Re-run: https://{$host}/modules/infrastructure-marketplace/Cli/production-verify.php\n";
    }
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
    'includes/ratib-public-base-url.php',
    'pages/about.php',
    'js/pages/ratib-mega-nav.js',
    'includes/ratib-public-base-url.php',
    'public/ratib-build.txt',
    'ratib-chrome-bust.php',
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
    if (ratib_has($sample, 'data-ratib-go-profile')) {
        $flags[] = 'go-profile';
    }
    if (ratib_has($sample, 'v13-onclick')) {
        $flags[] = 'v13-onclick';
    }
    if (ratib_has($sample, 'wireProfileLink')) {
        $flags[] = 'wireProfile';
    }
    $flagStr = $flags !== [] ? ' (' . implode(', ', $flags) . ')' : ' (OLD)';
    echo '[OK] ' . $rel . ' mtime=' . date('c', (int) filemtime($path)) . ' bytes=' . filesize($path) . $flagStr . "\n";
}

$build = $root . '/public/ratib-build.txt';
echo "\nbuild_marker=" . (is_file($build) ? trim((string) file_get_contents($build)) : 'missing') . "\n";
echo "expected_from_github=about-enterprise-20260518-profile-v13-onclick\n";

$liveHome = ratib_http_get('https://' . $host . '/pages/home.php');
echo "\n--- Live HTML vs disk (home.php) ---\n";
if ($liveHome === false) {
    echo "live_home=FAIL fetch\n";
} else {
    echo 'live_v13=' . (ratib_has($liveHome, 'ratib-profile-nav=v13-onclick') ? 'yes' : 'no') . "\n";
    echo 'live_go_profile=' . (ratib_has($liveHome, 'data-ratib-go-profile') ? 'yes' : 'no') . "\n";
    echo 'live_company_profile_href=' . (ratib_has($liveHome, '/pages/company-profile.php') ? 'yes (STALE CACHE)' : 'no') . "\n";
    echo 'live_head_lock=' . (ratib_has($liveHome, 'ratib-profile-head-lock') ? 'yes' : 'no') . "\n";
    if (preg_match('/brand-profile=v(\d+)/', $liveHome, $m)) {
        echo 'live_brand_profile_marker=v' . $m[1] . "\n";
    }
}
$chromeDisk = is_file($root . '/includes/ratib-home-public-chrome-top.php')
    ? (string) @file_get_contents($root . '/includes/ratib-home-public-chrome-top.php', false, null, 0, 16000)
    : '';
echo 'disk_chrome_v13=' . (ratib_has($chromeDisk, 'v13-onclick') ? 'yes' : 'no') . "\n";
echo 'disk_chrome_onclick=' . (ratib_has($chromeDisk, 'data-ratib-go-profile') ? 'yes' : 'no') . "\n";

$liveProfile = ratib_http_get('https://' . $host . '/profile/?_r=' . time());
echo "\n--- Live HTML vs disk (/profile/) ---\n";
if ($liveProfile === false) {
    echo "live_profile=FAIL fetch\n";
} else {
    echo 'live_distinct_banner=' . (ratib_has($liveProfile, 'ratib-profile-distinct-banner') ? 'yes' : 'no (STALE)') . "\n";
    echo 'live_about_title=' . (ratib_has($liveProfile, 'About <span class="ratib-about-gradient">Ratib Company</span>') ? 'yes (STALE)' : 'no') . "\n";
    echo 'live_profile_legal_stale=' . (ratib_has($liveProfile, 'Software Foundation for Information Technology') ? 'yes (STALE)' : 'no') . "\n";
    echo 'live_profile_trade_rateb=' . (ratib_has($liveProfile, 'ratib-company-dossier__title') && ratib_has($liveProfile, '>RATEB<') ? 'yes' : 'no (STALE)') . "\n";
    echo 'live_home_ratib_company=' . (ratib_has($liveHome ?? '', 'Ratib Company') ? 'yes (STALE)' : 'no') . "\n";
    echo 'live_page_stamp=' . (ratib_has($liveProfile, 'data-ratib-page-stamp="profile"') ? 'yes' : 'no') . "\n";
    echo 'live_home_hero_on_profile=' . (ratib_has($liveProfile, 'class="ratib-hero__title"') ? 'yes WRONG' : 'no') . "\n";
}
echo "\n>>> Visual checker (open in browser):\n";
echo "https://{$host}/pages/ratib-which-page.php\n";
echo ">>> Purge cache then open profile:\n";
echo "https://{$host}/pages/ratib-purge-cache.php?key=ratib-deploy-sync-2026\n";

if (ratib_has($chromeDisk, 'v13-onclick') && $liveHome !== false && !ratib_has($liveHome, 'ratib-profile-nav=v13-onclick')) {
    echo "CACHE_MISMATCH=yes → cPanel LiteSpeed Purge All, then:\n";
    echo "https://{$host}/pages/home.php?ratib_purge_lscache=1&key=ratib-deploy-sync-2026\n";
}

echo "\n--- PHP write probe (why curl deploy FAIL write) ---\n";
$phpUid = function_exists('posix_geteuid') ? (int) posix_geteuid() : -1;
$phpUser = ($phpUid >= 0 && function_exists('posix_getpwuid')) ? (posix_getpwuid($phpUid)['name'] ?? (string) $phpUid) : 'unknown';
echo 'php_effective_user=' . $phpUser . ' uid=' . $phpUid . "\n";
$probeFiles = ['public/ratib-build.txt', 'js/pages/ratib-mega-nav.js'];
foreach ($probeFiles as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        echo "{$rel}: missing\n";
        continue;
    }
    $ownUid = @fileowner($path);
    $ownUser = ($ownUid !== false && function_exists('posix_getpwuid')) ? (posix_getpwuid($ownUid)['name'] ?? (string) $ownUid) : '?';
    $mode = sprintf('%04o', @fileperms($path) & 07777);
    $writable = is_writable($path) ? 'yes' : 'no';
    $test = @file_put_contents($path, (string) @file_get_contents($path), LOCK_EX);
    echo "{$rel}: owner={$ownUser} mode={$mode} is_writable={$writable} php_rewrite=" . ($test !== false ? 'ok' : 'FAIL') . "\n";
}
$tmp = $root . '/public/.ratib-write-test-' . bin2hex(random_bytes(4));
$tmpOk = @file_put_contents($tmp, 'ok') !== false;
echo 'new_file_in_public=' . ($tmpOk ? 'ok' : 'FAIL') . "\n";
if ($tmpOk) {
    @unlink($tmp);
}
if ($phpUid >= 0 && is_file($build)) {
    $fUid = @fileowner($build);
    if ($fUid !== false && (int) $fUid !== $phpUid) {
        echo "hint=Files owned by another user; use cPanel File Manager or Git Deploy (not PHP curl).\n";
    }
}

echo "\n>>> Permission check (home 711, dirs 755, files 644):\n";
echo "https://{$host}/pages/ratib-perms-check.php\n";
echo "Auto-fix: https://{$host}/pages/ratib-perms-check.php?fix=1&key=ratib-deploy-sync-2026\n";
echo "\n>>> Legacy fix script:\n";
echo "https://{$host}/pages/ratib-fix-perms.php?run=1&key=ratib-deploy-sync-2026\n";
echo "\n>>> Then deploy Profile:\n";
echo "https://{$host}/pages/ratib-profile-deploy.php?deploy=1&key=ratib-deploy-sync-2026\n";
echo "\n>>> Chrome / cache diagnostic:\n";
echo "https://{$host}/ratib-chrome-bust.php\n";
echo "https://{$host}/pages/ratib-chrome-bust.php\n";
echo "\n>>> Apply CMS rebrand to database (stale Ratib Company / RATIB copy):\n";
echo "https://{$host}/pages/ratib-cms-rebrand-apply.php?key=ratib-deploy-sync-2026\n";
echo "https://{$host}/pages/ratib-cms-rebrand-apply.php?key=ratib-deploy-sync-2026&dry=1\n";
echo "\n>>> Purge LiteSpeed cache for home (after uploading .htaccess + home.php):\n";
echo "https://{$host}/pages/home.php?ratib_purge_lscache=1&key=ratib-deploy-sync-2026\n";
