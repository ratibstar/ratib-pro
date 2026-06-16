<?php
require_once __DIR__ . '/../includes/rateb-php74-compat.php';

/**
 * Public deploy probe — confirms which docroot serves rateb.sa and whether About files landed.
 * URL: https://rateb.sa/pages/deploy-root.php
 * Bundle: about-enterprise-20260516-v9
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$root = dirname(__DIR__);
$homePath = $root . '/pages/home.php';
$aboutPath = $root . '/pages/about.php';
$chromePath = $root . '/includes/rateb-home-public-chrome-top.php';
$bootstrapPath = $root . '/includes/rateb-home-public-nav-bootstrap.php';
$buildPath = $root . '/public/rateb-build.txt';

$homeSample = is_file($homePath) ? (string) file_get_contents($homePath, false, null, 0, 12000) : '';
$chromeSample = is_file($chromePath) ? (string) file_get_contents($chromePath, false, null, 0, 12000) : '';

echo "rateb-deploy-root\n";
echo 'host=' . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo 'document_root=' . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo 'probe_root=' . $root . "\n";
echo 'script=' . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";
echo 'git_marker=' . (is_file($buildPath) ? trim((string) file_get_contents($buildPath)) : 'missing') . "\n";
echo 'stamp_file=' . (is_file($root . '/.rateb-deploy-stamp') ? trim((string) file_get_contents($root . '/.rateb-deploy-stamp')) : 'missing') . "\n";
echo 'about_php=' . (is_file($aboutPath) ? 'yes bytes=' . filesize($aboutPath) : 'no') . "\n";
echo 'home_open_about=' . (str_contains($homeSample, "=== 'about'") ? 'yes' : 'no') . "\n";
echo 'chrome_about_link=' . (str_contains($chromeSample, 'rateb-nav__link--about') ? 'yes' : 'no') . "\n";
echo 'chrome_primary_links_8=' . (str_contains($chromeSample, 'primary-links=8') ? 'yes' : 'no') . "\n";
$bootstrapSample = is_file($bootstrapPath) ? (string) file_get_contents($bootstrapPath, false, null, 0, 12000) : '';
echo 'bootstrap_mtime=' . (is_file($bootstrapPath) ? (string) (int) filemtime($bootstrapPath) : '0') . "\n";
echo 'bootstrap_nav_asset_init=' . (str_contains($bootstrapSample, 'Initialize before any $GLOBALS') ? 'yes' : 'no') . "\n";
echo 'bootstrap_early_globals_bug=' . (preg_match('/\\$GLOBALS\\[\'ratebEnterpriseCalmCssQuery\'\\][^;]+;\\s*\\$ratebEnterpriseCalmCssPath/s', $bootstrapSample) === 1 ? 'yes' : 'no') . "\n";
echo 'bootstrap_preflight=' . (str_contains($bootstrapSample, 'rateb-nav-asset-preflight.php') ? 'yes' : 'no') . "\n";
echo 'nav_health_url=/pages/rateb-nav-health.php' . "\n";
echo 'chrome_profile_same_tab=' . (str_contains($chromeSample, 'rateb-profile-nav=same-tab-v2') ? 'yes' : 'no') . "\n";
echo 'home_mtime=' . (is_file($homePath) ? (string) (int) filemtime($homePath) : '0') . "\n";
echo 'about_mtime=' . (is_file($aboutPath) ? (string) (int) filemtime($aboutPath) : '0') . "\n";
$profileLanding = $root . '/profile/index.php';
echo 'profile_index_php=' . (is_file($profileLanding) ? 'yes bytes=' . filesize($profileLanding) : 'no') . "\n";
$htaccessPath = $root . '/.htaccess';
$htaccessSample = is_file($htaccessPath) ? (string) file_get_contents($htaccessPath, false, null, 0, 8000) : '';
echo 'htaccess_profile_about=' . (str_contains($htaccessSample, 'RewriteRule ^profile/$ pages/about.php') ? 'yes' : 'no') . "\n";
echo 'htaccess_profile_index=' . (str_contains($htaccessSample, 'profile/index.php') ? 'yes' : 'no') . "\n";
$aboutSample = is_file($aboutPath) ? (string) file_get_contents($aboutPath, false, null, 0, 16000) : '';
echo 'about_distinct_banner=' . (str_contains($aboutSample, 'rateb-profile-distinct-banner') ? 'yes' : 'no') . "\n";
echo 'about_page_stamp=' . (str_contains($aboutSample, 'rateb-page-stamp') ? 'yes' : 'no') . "\n";
echo 'about_company_dossier=' . (str_contains($aboutSample, 'id="company-profile"') ? 'yes' : 'no') . "\n";

