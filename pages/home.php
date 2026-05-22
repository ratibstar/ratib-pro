<?php
/**
 * Public: Home / landing page — English, layout like ratib.sa reference.
 * EN: Prepares server-side values (plans/currency/assets), renders page sections, and bootstraps JS config.
 * AR: يجهّز قيم السيرفر (الخطط/العملة/الأصول)، ويعرض أقسام الصفحة، ثم يمرر إعدادات JavaScript.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ratib-public-base-url.php';
/** Platform pills on this page use in-page #anchors (no full reload). */
$GLOBALS['ratib_public_nav_on_marketing_home'] = true;

// LiteSpeed caches bare /pages/home.php with old Profile → new-tab HTML. Require ?v= build marker.
$ratibHomeSkipBuildBust = isset($_GET['ratib_deploy_probe'])
    || (
        isset($_GET['ratib_purge_lscache'], $_GET['key'])
        && (string) $_GET['ratib_purge_lscache'] === '1'
        && hash_equals('ratib-deploy-sync-2026', (string) $_GET['key'])
    );
if (!$ratibHomeSkipBuildBust) {
    $ratibBuildMarker = ratib_public_build_marker();
    $ratibReqBuildV = isset($_GET['v']) ? trim((string) $_GET['v']) : '';
    if ($ratibBuildMarker !== '' && !headers_sent()) {
        $needsCanonicalV = $ratibReqBuildV === ''
            || !ratib_public_build_marker_is_valid($ratibReqBuildV)
            || $ratibReqBuildV !== $ratibBuildMarker;
        if ($needsCanonicalV) {
            $qs = $_GET;
            $qs['v'] = $ratibBuildMarker;
            $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/pages/home.php'), PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? $path : '/pages/home.php';
            $dest = $path . '?' . http_build_query($qs);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Redirect</title>';
            echo '<script>location.replace(' . json_encode($dest, JSON_UNESCAPED_SLASHES) . '+location.hash);</script>';
            echo '</head><body></body></html>';
            exit;
        }
    }
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('X-LiteSpeed-Cache-Control: no-cache', false);
}

if (function_exists('opcache_invalidate')) {
    $ratibOpcacheBust = [
        __DIR__ . '/../includes/ratib-home-public-chrome-top.php',
        __DIR__ . '/../includes/ratib-home-public-nav-sync.php',
        __DIR__ . '/../includes/ratib-home-public-nav-bootstrap.php',
        __DIR__ . '/../includes/ratib-site-content-rebrand-sanitize.php',
        __DIR__ . '/../includes/site-content-home-data.php',
        __FILE__,
    ];
    foreach ($ratibOpcacheBust as $ratibOpcacheFile) {
        if (is_file($ratibOpcacheFile)) {
            opcache_invalidate($ratibOpcacheFile, true);
        }
    }
}

// Deploy probe: /pages/home.php?ratib_deploy_probe=1 (bundle about-enterprise-20260516-v9)
if (isset($_GET['ratib_deploy_probe']) && (string) $_GET['ratib_deploy_probe'] === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $probeRoot = dirname(__DIR__);
    $aboutPath = $probeRoot . '/pages/about.php';
    $chromePath = $probeRoot . '/includes/ratib-home-public-chrome-top.php';
    $buildPath = $probeRoot . '/public/ratib-build.txt';
    $homeSample = is_file(__FILE__) ? (string) file_get_contents(__FILE__, false, null, 0, 12000) : '';
    $chromeSample = is_file($chromePath) ? (string) file_get_contents($chromePath, false, null, 0, 16000) : '';
    echo "ratib-deploy-probe-via-home\n";
    echo 'chrome_onclick_disk=' . (stripos($chromeSample, 'data-ratib-go-profile') !== false ? 'yes' : 'no') . "\n";
    echo 'chrome_v13_disk=' . (stripos($chromeSample, 'brand-profile=v13-onclick') !== false ? 'yes' : 'no') . "\n";
    echo 'document_root=' . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
    echo 'probe_root=' . $probeRoot . "\n";
    echo 'git_marker=' . (is_file($buildPath) ? trim((string) file_get_contents($buildPath)) : 'missing') . "\n";
    $companyProfilePath = $probeRoot . '/pages/company-profile.php';
    echo 'about_php=' . (is_file($aboutPath) ? 'yes' : 'no') . "\n";
    echo 'company_profile_php=' . (is_file($companyProfilePath) ? 'yes' : 'no') . "\n";
    echo 'home_open_about=' . (str_contains($homeSample, "=== 'about'") ? 'yes' : 'no') . "\n";
    echo 'chrome_about_link=' . (str_contains($chromeSample, 'ratib-nav__link--about') ? 'yes' : 'no') . "\n";
    echo 'stamp_file=' . (is_file($probeRoot . '/.ratib-deploy-stamp') ? trim((string) file_get_contents($probeRoot . '/.ratib-deploy-stamp')) : 'missing') . "\n";
    exit;
}

// Company profile — /profile (canonical) or legacy ?open=profile|about on home.php
$ratibOpenParam = isset($_GET['open']) ? trim((string) $_GET['open']) : '';
if ($ratibOpenParam === 'about' || $ratibOpenParam === 'profile') {
    $ratibPath = $_SERVER['REQUEST_URI'] ?? '';
    $ratibBasePath = preg_replace('#/pages/[^?]*.*$#', '', $ratibPath) ?: '';
    $ratibScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $ratibHost = $_SERVER['HTTP_HOST'] ?? '';
    header('Location: ' . $ratibScheme . '://' . $ratibHost . $ratibBasePath . '/profile', true, 302);
    exit;
}

// Optional: /pages/home.php?ratib_purge_lscache=1&key=ratib-deploy-sync-2026 — ask LiteSpeed to purge this vhost cache.
if (
    isset($_GET['ratib_purge_lscache'], $_GET['key'])
    && (string) $_GET['ratib_purge_lscache'] === '1'
    && hash_equals('ratib-deploy-sync-2026', (string) $_GET['key'])
    && !headers_sent()
) {
    header('X-LiteSpeed-Purge: *');
    header('X-LiteSpeed-Cache-Control: no-cache');
}
// Always bust LiteSpeed page cache for marketing home (stale HTML had profile → new tab).
if (!headers_sent()) {
    header('X-LiteSpeed-Cache-Control: no-cache', false);
    header('X-LiteSpeed-Tag: ratib-home-' . date('YmdHi'), false);
}

// Prevent stale HTML caching (browser + reverse proxies + LiteSpeed).
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('X-Ratib-Home-Nav: v13-onclick');
    header('X-LiteSpeed-Cache-Control: no-cache', false);
    header('X-LiteSpeed-Tag: ratib-home-nocache', false);
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    // Surrogate-Control: respected by Cloudflare / Fastly-style edges when configured to honor origin.
    header('Surrogate-Control: no-store');
    // Cloudflare: separate CDN TTL from browser (Business+ sometimes); harmless if ignored.
    header('CDN-Cache-Control: no-store');
    header('Vary: Accept-Encoding', false);
}

// EN: Read checkout currency/exchange settings from environment with safe defaults.
// AR: قراءة إعدادات عملة الدفع وسعر التحويل من البيئة مع قيم افتراضية آمنة.
if (!function_exists('ratib_ngenius_env')) {
    require_once __DIR__ . '/../config/env.php';
}
$ratibCheckoutCurrency = 'SAR';
$ratibUsdToSar = 3.75;
if (function_exists('ratib_ngenius_env')) {
    $ratibCheckoutCurrency = strtoupper(trim((string) ratib_ngenius_env('NGENIUS_CHECKOUT_CURRENCY', 'SAR'))) ?: 'SAR';
    $ratibUsdToSar = (float) ratib_ngenius_env('NGENIUS_USD_TO_SAR', '3.75');
}
if (!is_finite($ratibUsdToSar) || $ratibUsdToSar <= 0) {
    $ratibUsdToSar = 3.75;
}
$ratibDisplayCheckoutCurrency = $ratibCheckoutCurrency;
$ratibDisplayNgeniusLabel = ($ratibCheckoutCurrency === 'SAR') ? 'N-Genius KSA' : 'N-Genius';
$ratibDefaultUsdRates = [
    'USD' => 1.00,
    'SAR' => 3.75,
    'BDT' => 117.50,
    'IDR' => 16000.00,
    'ETB' => 57.00,
    'PHP' => 57.00,
    'KES' => 129.00,
    'UGX' => 3800.00,
    'NGN' => 1450.00,
    'RWF' => 1300.00,
    'LKR' => 300.00,
    'NPR' => 133.00,
    'THB' => 36.00,
];
$countryCurrencyByCode = [
    'BD' => 'BDT',
    'ET' => 'ETB',
    'PH' => 'PHP',
    'KE' => 'KES',
    'ID' => 'IDR',
    'UG' => 'UGX',
    'NG' => 'NGN',
    'RW' => 'RWF',
    'LK' => 'LKR',
    'NP' => 'NPR',
    'TH' => 'THB',
];
$countryCurrencyByName = [
    'BANGLADESH' => 'BDT',
    'ETHIOPIA' => 'ETB',
    'PHILIPPINES' => 'PHP',
    'KENYA' => 'KES',
    'INDONESIA' => 'IDR',
    'UGANDA' => 'UGX',
    'NIGERIA' => 'NGN',
    'RWANDA' => 'RWF',
    'SRI LANKA' => 'LKR',
    'NEPAL' => 'NPR',
    'THAILAND' => 'THB',
];
$countryCurrencyBySlug = [
    'bangladesh' => 'BDT',
    'ethiopia' => 'ETB',
    'philippines' => 'PHP',
    'kenya' => 'KES',
    'indonesia' => 'IDR',
    'uganda' => 'UGX',
    'nigeria' => 'NGN',
    'rwanda' => 'RWF',
    'sri-lanka' => 'LKR',
    'srilanka' => 'LKR',
    'nepal' => 'NPR',
    'thailand' => 'THB',
];
$countryNameByCode = [
    'BD' => 'Bangladesh',
    'UG' => 'Uganda',
    'KE' => 'Kenya',
    'LK' => 'Sri Lanka',
    'PH' => 'Philippines',
    'ID' => 'Indonesia',
    'ET' => 'Ethiopia',
    'NG' => 'Nigeria',
    'RW' => 'Rwanda',
    'TH' => 'Thailand',
    'NP' => 'Nepal',
];
$countryNameBySlug = [
    'bangladesh' => 'Bangladesh',
    'uganda' => 'Uganda',
    'kenya' => 'Kenya',
    'sri-lanka' => 'Sri Lanka',
    'srilanka' => 'Sri Lanka',
    'philippines' => 'Philippines',
    'indonesia' => 'Indonesia',
    'ethiopia' => 'Ethiopia',
    'nigeria' => 'Nigeria',
    'rwanda' => 'Rwanda',
    'thailand' => 'Thailand',
    'nepal' => 'Nepal',
];
$countryCodeRaw = strtoupper(trim((string) ($_GET['country_code'] ?? ($_SESSION['country_code'] ?? ''))));
$countryNameRaw = strtoupper(trim((string) ($_GET['country_name'] ?? $_GET['country'] ?? ($_SESSION['country_name'] ?? ''))));
$countrySlugRaw = strtolower(trim((string) ($_GET['country_slug'] ?? '')));
if ($countrySlugRaw === '') {
    $ref = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref !== '') {
        $refPath = (string) parse_url($ref, PHP_URL_PATH);
        $refPath = trim($refPath, '/');
        if ($refPath !== '') {
            $firstSeg = strtolower((string) strtok($refPath, '/'));
            if ($firstSeg !== '' && isset($countryCurrencyBySlug[$firstSeg])) {
                $countrySlugRaw = $firstSeg;
            }
        }
    }
}
if ($countryCodeRaw !== '' && isset($countryCurrencyByCode[$countryCodeRaw])) {
    $ratibDisplayCheckoutCurrency = $countryCurrencyByCode[$countryCodeRaw];
} elseif ($countryNameRaw !== '' && isset($countryCurrencyByName[$countryNameRaw])) {
    $ratibDisplayCheckoutCurrency = $countryCurrencyByName[$countryNameRaw];
} elseif ($countrySlugRaw !== '' && isset($countryCurrencyBySlug[$countrySlugRaw])) {
    $ratibDisplayCheckoutCurrency = $countryCurrencyBySlug[$countrySlugRaw];
}
if ($ratibDisplayCheckoutCurrency !== 'SAR') {
    $ratibDisplayNgeniusLabel = 'N-Genius ' . $ratibDisplayCheckoutCurrency;
}
$ratibDisplayUsdRate = $ratibDefaultUsdRates[$ratibDisplayCheckoutCurrency] ?? 1.00;
$ratibDisplayRateKey = 'NGENIUS_USD_TO_' . preg_replace('/[^A-Z]/', '', $ratibDisplayCheckoutCurrency);
$ratibDisplayUsdRateEnv = (float) ratib_ngenius_env($ratibDisplayRateKey, (string) $ratibDisplayUsdRate);
if (is_finite($ratibDisplayUsdRateEnv) && $ratibDisplayUsdRateEnv > 0) {
    $ratibDisplayUsdRate = $ratibDisplayUsdRateEnv;
}
$ratibLockedCountryName = '';
if ($countryCodeRaw !== '' && isset($countryNameByCode[$countryCodeRaw])) {
    $ratibLockedCountryName = $countryNameByCode[$countryCodeRaw];
} elseif ($countrySlugRaw !== '' && isset($countryNameBySlug[$countrySlugRaw])) {
    $ratibLockedCountryName = $countryNameBySlug[$countrySlugRaw];
} elseif ($countryNameRaw !== '') {
    $countryNameTitle = ucwords(strtolower($countryNameRaw));
    if ($countryNameTitle !== '') {
        $ratibLockedCountryName = $countryNameTitle;
    }
}

require_once __DIR__ . '/../includes/ratib-public-base-url.php';
$baseUrl = ratib_public_site_base_url();
$ratibDomainsIframeSrc = $baseUrl . '/modules/infrastructure-marketplace/Views/marketplace/index.php?focus=domains&embed=1#infra-domain-search';

// EN: Shared paths for gallery images and legacy hero video fallback (assets/*.mp4 when CMS has no clips).
$assetsDir = __DIR__ . '/../assets';

// EN: Build gallery image list from assets/images for dynamic rendering in the page.
// AR: تجهيز قائمة صور المعرض من assets/images لعرضها ديناميكياً في الصفحة.
// Images gallery: place jpg, jpeg, png, webp in assets/images/
$imagesDir = __DIR__ . '/../assets/images';
$galleryImages = [];
if (is_dir($imagesDir)) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    foreach (scandir($imagesDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) $galleryImages[] = $baseUrl . '/assets/images/' . rawurlencode($f);
    }
}
// Registration form (same as agency-request)
$openRegister = isset($_GET['open']) && trim((string) ($_GET['open'] ?? '')) === 'register';
// Public link is often ?open=register with no plan — default to gold so N-Genius create-order accepts it (gold/platinum only).
$planRaw = isset($_GET['plan']) ? trim((string) $_GET['plan']) : '';
$plan = $planRaw !== '' ? $planRaw : ($openRegister ? 'gold' : 'pro');
if ($plan === '') {
    $plan = 'pro';
}
$goldTestPriceYear1 = 5;
$goldTestPriceMonth = 4.5;
$platinumTestPriceYear1 = 800;
$platinumTestPriceMonth = 67;
$goldListPriceYear1 = $goldTestPriceYear1 * 2;
$goldListPriceMonth = $goldTestPriceMonth * 2;
$platinumListPriceYear1 = $platinumTestPriceYear1 * 2;
$platinumListPriceMonth = $platinumTestPriceMonth * 2;
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : null;
$years = isset($_GET['years']) ? (int)$_GET['years'] : null;
// EN: Only monthly (0) and one-year (1) tiers are offered; normalize any legacy/invalid value to one year.
if ($years !== null && $years !== 0 && $years !== 1) {
    $years = 1;
}
$plans = ['gold' => ['label' => 'Gold', 'amount' => $goldTestPriceYear1], 'platinum' => ['label' => 'Platinum', 'amount' => $platinumTestPriceYear1], 'pro' => ['label' => 'Pro', 'amount' => null]];
$planLabel = $plans[$plan]['label'] ?? ucfirst($plan);
// EN: Amount follows optional URL years: 0 = monthly, 1 = annual (Gold/Platinum only).
// AR: المبلغ يتبع مدة years في الرابط: 0 شهري، 1 سنوي.
$planAmount = ($amount !== null) ? $amount : null;
if ($planAmount === null && isset($plans[$plan])) {
    if (($plan === 'gold' || $plan === 'platinum') && $years !== null) {
        $y = (int) $years;
        if ($y === 0) {
            $planAmount = $plan === 'gold' ? $goldTestPriceMonth : $platinumTestPriceMonth;
        } elseif ($y === 1) {
            $planAmount = $plan === 'gold' ? $goldTestPriceYear1 : $platinumTestPriceYear1;
        } else {
            $planAmount = $plans[$plan]['amount'] ?? null;
        }
    } else {
        $planAmount = $plans[$plan]['amount'] ?? null;
    }
}
$countries = ['Bangladesh', 'Uganda', 'Kenya', 'Sri Lanka', 'Philippines', 'Indonesia', 'Ethiopia', 'Nigeria', 'Rwanda', 'Thailand', 'Nepal', 'Other countries sending workers'];
$ratibCountryIsLocked = ($ratibLockedCountryName !== '');
if ($ratibCountryIsLocked && !in_array($ratibLockedCountryName, $countries, true)) {
    array_unshift($countries, $ratibLockedCountryName);
}

require_once __DIR__ . '/../includes/site-content.php';
// Canonicalize homepage URL to current CMS revision so browser/CDN tabs don't stick to stale HTML across saves.
$ratibCmsRev = function_exists('ratib_site_content_revision_token') ? ratib_site_content_revision_token() : '';
if ($ratibCmsRev !== '') {
    $currentRev = isset($_GET['cms_rev']) ? (string) $_GET['cms_rev'] : '';
    if ($currentRev !== $ratibCmsRev && !headers_sent()) {
        $qs = $_GET;
        $qs['cms_rev'] = $ratibCmsRev;
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/pages/home.php'), PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/pages/home.php';
        $newUrl = $path . '?' . http_build_query($qs);
        header('Location: ' . $newUrl, true, 302);
        exit;
    }
}
require_once __DIR__ . '/../includes/ratib-home-public-nav-bootstrap.php';

$ratibEntTrustInclude = __DIR__ . '/../includes/ratib-enterprise-trust-home.php';
if (is_file($ratibEntTrustInclude)) {
    require_once $ratibEntTrustInclude;
} elseif (!function_exists('ratib_enterprise_trust_render_home')) {
    function ratib_enterprise_trust_render_home(array $ratibHome, string $baseUrl): void
    {
    }
    function ratib_enterprise_trust_render_hero_strip(array $ratibHome): void
    {
    }
}
if (!function_exists('ratib_enterprise_mailto')) {
    function ratib_enterprise_mailto(string $subject): string
    {
        return 'mailto:info@out.ratib.sa?subject=' . rawurlencode($subject);
    }
}

$ratibOpProofInclude = __DIR__ . '/../includes/ratib-operational-proof-render.php';
$ratibOpProofAvailable = is_file($ratibOpProofInclude);
if ($ratibOpProofAvailable) {
    require_once $ratibOpProofInclude;
} elseif (!function_exists('ratib_operational_proof_render')) {
    function ratib_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void
    {
    }
}

$ratibEntCssPath = __DIR__ . '/../css/pages/enterprise-trust-layer.css';
clearstatcache(true, $ratibEntCssPath);
$ratibEntCssQuery = (int) (@filemtime($ratibEntCssPath) ?: time()) . '-' . $ratibHomeUiRev . '-c' . $ratibChromeBundleHash;
$ratibEntCssAvailable = is_file($ratibEntCssPath);
$ratibOpCssPath = __DIR__ . '/../css/pages/operational-proof.css';
clearstatcache(true, $ratibOpCssPath);
$ratibOpCssQuery = (int) (@filemtime($ratibOpCssPath) ?: time()) . '-' . $ratibHomeUiRev . '-c' . $ratibChromeBundleHash;
$ratibOpCssAvailable = is_file($ratibOpCssPath);
$ratibMarketingFocusedCssPath = __DIR__ . '/../css/pages/home-marketing-focused.css';
clearstatcache(true, $ratibMarketingFocusedCssPath);
$ratibMarketingFocusedCssQuery = (int) (@filemtime($ratibMarketingFocusedCssPath) ?: time()) . '-' . $ratibHomeUiRev;
$ratibMarketingFocusedJsPath = __DIR__ . '/../js/pages/ratib-marketing-focused.js';
clearstatcache(true, $ratibMarketingFocusedJsPath);
$ratibMarketingFocusedJsQuery = (int) (@filemtime($ratibMarketingFocusedJsPath) ?: time()) . '-' . $ratibHomeUiRev;
$ratibSiteRoot = rtrim($baseUrl, '/');
$ratibHomeAnchor = static function (string $hash): string {
    return function_exists('ratib_public_marketing_home_anchor')
        ? ratib_public_marketing_home_anchor($hash)
        : ($hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#'));
};
$ratibRegisterHref = $ratibHomeAnchor('#register');
$ratibHeroTourHref = $ratibHomeAnchor('#video');
$ratibArchSectionsOk = is_file(__DIR__ . '/../includes/ratib-architecture-sections.php');
$ratibWalkthroughHref = $ratibArchSectionsOk
    ? $ratibSiteRoot . '/architecture/'
    : $ratibHomeAnchor('#enterprise-infrastructure');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <!-- ratib-cms-build: site-content=<?php echo (int) (@filemtime(__DIR__ . '/../includes/site-content.php') ?: 0); ?> home-data=<?php echo (int) (@filemtime(__DIR__ . '/../includes/site-content-home-data.php') ?: 0); ?> rebrand=<?php echo htmlspecialchars(trim((string) (@file_get_contents(__DIR__ . '/../public/ratib-build.txt') ?: '')), ENT_QUOTES, 'UTF-8'); ?> load=<?php echo (int) (@filemtime(__DIR__ . '/../config/env/load.php') ?: 0); ?> cms-src=<?php echo htmlspecialchars(function_exists('ratib_site_content_public_source_resolved') ? ratib_site_content_public_source_resolved() : '', ENT_QUOTES, 'UTF-8'); ?> phone-len=<?php echo (int) strlen((string) ($ratibHome['home.topbar.phone_display'] ?? '')); ?> dbfp=<?php echo htmlspecialchars($ratibDbFingerprint, ENT_QUOTES, 'UTF-8'); ?> ui-rev=<?php echo htmlspecialchars($ratibHomeUiRev, ENT_QUOTES, 'UTF-8'); ?> -->
    <meta charset="UTF-8">
    <?php
    require_once __DIR__ . '/../includes/ratib-profile-force-same-tab.php';
    ratib_emit_profile_force_same_tab($baseUrl);
    ratib_home_nav_emit_sync_guard_style();
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="ratib-home-ui-rev" content="<?php echo htmlspecialchars($ratibHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="ratib-chrome-bundle" content="<?php echo htmlspecialchars($ratibChromeBundleHash, ENT_QUOTES, 'UTF-8'); ?>">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title><?php echo htmlspecialchars($ratibHome['home.meta.page_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></title>
    <?php
    $ratibHomeMetaDesc = trim((string) ($ratibHome['home.hero.lead'] ?? 'Recruitment orchestration, workforce telemetry, compliance, and finance-grade operations on one multi-tenant control plane.'));
    $ratibHomeCanonical = rtrim($baseUrl, '/') . '/';
    ?>
    <meta name="description" content="<?php echo htmlspecialchars($ratibHomeMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars((string) ($ratibHome['home.meta.page_title'] ?? 'RATEB — Enterprise Workforce Program Infrastructure'), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($ratibHomeMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($ratibHomeCanonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars((string) ($ratibHome['home.meta.page_title'] ?? 'RATEB — Enterprise Workforce Program Infrastructure'), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($ratibHomeMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/chat-widget.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-public.css?v=<?php echo htmlspecialchars($ratibHomePublicCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($ratibEntCssAvailable) { ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/enterprise-trust-layer.css?v=<?php echo htmlspecialchars($ratibEntCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php } ?>
    <?php if ($ratibOpCssAvailable) { ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/operational-proof.css?v=<?php echo htmlspecialchars($ratibOpCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php } ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/ratib-mega-nav.css?v=<?php echo htmlspecialchars($ratibMegaNavCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-marketing-focused.css?v=<?php echo htmlspecialchars($ratibMarketingFocusedCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (function_exists('ratib_marketing_emit_focused_rescue_css')) {
        ratib_marketing_emit_focused_rescue_css();
    } ?>
    <style id="ratib-nav-css-fallback">
      /* Layout-only rescue — no fixed icon sizes here (!important would override home-public / ratib-mega-nav). */
      #ratibNavMenu .ratib-nav__platform-links .ratib-nav__link{display:inline-flex!important;align-items:center!important;gap:.5rem!important}
      #ratibNavMenu .ratib-nav__platform-links .ratib-nav__icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important}
      #ratibNavMenu .ratib-nav__platform-links .ratib-nav__glyph{display:block!important}
      .ratib-nav__partner-login{display:inline-flex!important;align-items:center!important;gap:.45rem!important}
      .ratib-nav__partner-icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;width:2.2rem!important;height:2.2rem!important}
    </style>
</head>
<body class="ratib-saas-home <?php echo htmlspecialchars(ratib_public_marketing_density_body_class(), ENT_QUOTES, 'UTF-8'); ?>" data-ratib-marketing-density="<?php echo htmlspecialchars(ratib_public_marketing_density(), ENT_QUOTES, 'UTF-8'); ?>" data-ratib-home-layout="video-hero-program-svgs" data-ratib-home-ui-rev="<?php echo htmlspecialchars($ratibHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>" data-ratib-deploy="<?php echo htmlspecialchars($ratibHomePhpMtime . '-' . $ratibHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>">

<?php
include __DIR__ . '/../includes/ratib-home-public-chrome-top.php';
require_once __DIR__ . '/../includes/ratib-profile-nav-guard.php';
ratib_emit_profile_nav_guard($baseUrl);
?>

    <main class="ratib-main">
        <!-- RATEB public home layout: product tour video directly under hero grid; program preview SVGs below. Deploy fingerprint: search HTML for id="video" on hero band + data-ratib-home-layout on body. -->
        <section class="ratib-hero">
            <div class="ratib-container ratib-hero__grid">
                <div class="ratib-hero__copy">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.hero.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h1 class="ratib-hero__title"><?php echo htmlspecialchars($ratibHome['home.hero.title_before'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="ratib-text-gradient"><?php echo htmlspecialchars($ratibHome['home.hero.title_gradient'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></h1>
                    <p class="ratib-hero__lead"><?php echo htmlspecialchars($ratibHome['home.hero.lead'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <ul class="ratib-hero__bullets">
                        <li><i class="fas fa-diagram-project"></i> <?php echo htmlspecialchars($ratibHome['home.hero.bullet.1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
                        <li><i class="fas fa-building-user"></i> <?php echo htmlspecialchars($ratibHome['home.hero.bullet.2'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
                        <li><i class="fas fa-location-crosshairs"></i> <?php echo htmlspecialchars($ratibHome['home.hero.bullet.3'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
                        <li><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($ratibHome['home.hero.bullet.4'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
                    </ul>
                    <?php if (!function_exists('ratib_public_marketing_should_render_deep') || ratib_public_marketing_should_render_deep()) {
                        ratib_enterprise_trust_render_hero_strip($ratibHome);
                    } ?>
                    <div class="ratib-hero__actions">
                        <a href="<?php echo htmlspecialchars(ratib_enterprise_mailto('RATEB — Request Enterprise Demo'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-btn ratib-btn--primary ratib-btn--lg"><?php echo htmlspecialchars($ratibHome['home.hero.cta_primary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                        <a href="<?php echo htmlspecialchars($ratibWalkthroughHref, ENT_QUOTES, 'UTF-8'); ?>" class="ratib-btn ratib-btn--outline ratib-btn--lg"><?php echo htmlspecialchars($ratibHome['home.hero.cta_secondary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                        <a href="<?php echo htmlspecialchars($ratibHeroTourHref, ENT_QUOTES, 'UTF-8'); ?>" class="ratib-btn ratib-btn--ghost ratib-btn--lg"><i class="fas fa-play" aria-hidden="true"></i> <?php echo htmlspecialchars($ratibHome['home.nav.tour'] ?? 'Tour', ENT_QUOTES, 'UTF-8'); ?></a>
                    </div>
                </div>
                <?php if (!function_exists('ratib_public_marketing_should_render_deep') || ratib_public_marketing_should_render_deep()) { ?>
                <div class="ratib-hero__visual" aria-hidden="true" data-ratib-marketing-depth="deep">
                    <div class="ratib-dash">
                        <div class="ratib-dash__chrome">
                            <div class="ratib-dash__chrome-main">
                                <span class="ratib-dash__dot"></span><span class="ratib-dash__dot"></span><span class="ratib-dash__dot"></span>
                                <span class="ratib-dash__title">RATEB Command</span>
                                <span class="ratib-dash__live" title="Sample UI — illustrative"><span class="ratib-live-dot"></span> Sample</span>
                            </div>
                            <div class="ratib-dash__chrome-sub ratib-mono-ops">
                                <span class="ratib-env-tag">prod</span>
                                <span class="ratib-dash__sep">·</span>
                                <span class="ratib-dash__panel-id" title="Sample workspace UI">ws-demo-01</span>
                                <span class="ratib-dash__sep">·</span>
                                <span class="ratib-dash__sync"><span class="ratib-sync-label">Edge sync</span> <span class="ratib-live-sync-age">2m</span></span>
                                <span class="ratib-dash__sep">·</span>
                                <span title="UTC session clock">UTC <time class="ratib-live-clock" datetime=""></time></span>
                            </div>
                        </div>
                        <p class="ratib-dash__illus ratib-mono-ops">Sample workspace UI · illustrative metrics</p>
                        <div class="ratib-dash__body">
                            <div class="ratib-dash__sidebar">
                                <div class="ratib-dash__nav-item ratib-dash__nav-item--active">Pipeline</div>
                                <div class="ratib-dash__nav-item">Workforce</div>
                                <div class="ratib-dash__nav-item">Agencies</div>
                                <div class="ratib-dash__nav-item">Finance</div>
                            </div>
                            <div class="ratib-dash__main">
                                <div class="ratib-dash__row">
                                    <div class="ratib-kpi" title="Cohort in active lifecycle states">
                                        <span class="ratib-kpi__label">Active workers</span>
                                        <span class="ratib-kpi__value ratib-live-nudge" data-ratib-jitter="2847">2,847</span>
                                        <span class="ratib-kpi__delta ratib-kpi__delta--up">+18% WoW</span>
                                    </div>
                                    <div class="ratib-kpi" title="Committed lifecycle transitions · rolling 24h">
                                        <span class="ratib-kpi__label">Stage commits (24h)</span>
                                        <span class="ratib-kpi__value">412</span>
                                        <span class="ratib-kpi__delta">event_log · shard A</span>
                                    </div>
                                    <div class="ratib-kpi" title="Stage SLAs met vs policy clock">
                                        <span class="ratib-kpi__label">SLA adherence</span>
                                        <span class="ratib-kpi__value ratib-live-nudge" data-ratib-jitter-pct="94.6">94.6%</span>
                                        <span class="ratib-kpi__delta ratib-kpi__delta--up">within policy</span>
                                    </div>
                                </div>
                                <div class="ratib-dash__signals">
                                    <span class="ratib-signal ratib-signal--ok" title="No breached stage clocks in this shard"><i class="fas fa-shield-halved" aria-hidden="true"></i> SLA policy OK</span>
                                    <span class="ratib-signal ratib-signal--ok" title="Document verification queue depth within SLO"><i class="fas fa-file-circle-check" aria-hidden="true"></i> KYC queue stable</span>
                                    <span class="ratib-signal ratib-signal--ok" title="Orchestrator round-trip p95"><i class="fas fa-gauge-high" aria-hidden="true"></i> ORCH p95 238ms</span>
                                </div>
                                <div class="ratib-dash__toolbar ratib-mono-ops">
                                    <span>Reconcile <span class="ratib-live-sync-age">2m</span></span>
                                    <span class="ratib-dash__sep">·</span>
                                    <span>Viewer <strong class="ratib-dash__strong">ops.supervisor</strong></span>
                                    <span class="ratib-dash__sep">·</span>
                                    <span class="ratib-pill ratib-pill--subtle">policy CL-2024-ME</span>
                                </div>
                                <p class="ratib-dash__context ratib-mono-ops">Pinned file <strong>WKR-ME-44821</strong> · corr <span class="ratib-dash__corr">ae7f9c2</span> · Medical clearance window</p>
                                <div class="ratib-dash__workspace">
                                    <div class="ratib-dash__panel ratib-dash__panel--table">
                                        <div class="ratib-dash__panel-head">
                                            <span>Workforce records</span>
                                            <span class="ratib-pill ratib-pill--subtle">tenant ACME · shard A</span>
                                        </div>
                                        <div class="ratib-dash-table-scroll">
                                            <table class="ratib-dash-table">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">Ref</th>
                                                        <th scope="col">Stage</th>
                                                        <th scope="col">SLA</th>
                                                        <th scope="col">Owner</th>
                                                        <th scope="col">Updated</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>WKR-ME-44821</td>
                                                        <td><span class="ratib-dash-tag ratib-dash-tag--warn">Medical</span></td>
                                                        <td class="ratib-dash-num">38h left</td>
                                                        <td>n.alharbi</td>
                                                        <td class="ratib-dash-num">14:06</td>
                                                    </tr>
                                                    <tr>
                                                        <td>WKR-UG-90213</td>
                                                        <td><span class="ratib-dash-tag ratib-dash-tag--idle">Embassy</span></td>
                                                        <td class="ratib-dash-num">queued</td>
                                                        <td>queue</td>
                                                        <td class="ratib-dash-num">13:58</td>
                                                    </tr>
                                                    <tr>
                                                        <td>WKR-BD-77104</td>
                                                        <td><span class="ratib-dash-tag ratib-dash-tag--ok">Visa</span></td>
                                                        <td class="ratib-dash-num">OK</td>
                                                        <td>s.rehman</td>
                                                        <td class="ratib-dash-num">13:41</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="ratib-dash__sidecol">
                                        <div class="ratib-dash__panel ratib-dash__panel--stack">
                                            <div class="ratib-dash__panel-head">
                                                <span>Verification queue</span>
                                                <span class="ratib-pill">depth 4</span>
                                            </div>
                                            <ul class="ratib-dash-qlist">
                                                <li><span class="ratib-q-type">Med bundle</span><span class="ratib-q-meta">review · est 11m</span></li>
                                                <li><span class="ratib-q-type">Emb. appointment</span><span class="ratib-q-meta">await scan</span></li>
                                                <li><span class="ratib-q-type">Police cert</span><span class="ratib-q-meta">OCR hold</span></li>
                                            </ul>
                                        </div>
                                        <div class="ratib-dash__panel ratib-dash__panel--stack">
                                            <div class="ratib-dash__panel-head">
                                                <span>Operational alerts</span>
                                                <span class="ratib-pill ratib-pill--subtle">last 1h</span>
                                            </div>
                                            <ul class="ratib-dash-alerts">
                                                <li class="ratib-dash-alerts__item ratib-dash-alerts__item--info"><span class="ratib-dash-alerts__sev">INFO</span> FIN webhook ACK · 312ms</li>
                                                <li class="ratib-dash-alerts__item ratib-dash-alerts__item--warn"><span class="ratib-dash-alerts__sev">WARN</span> Embassy slot drift · agency B</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="ratib-dash__footer">
                                    <div class="ratib-mapstrip">
                                        <i class="fas fa-location-crosshairs" aria-hidden="true"></i>
                                        <span>GPS · Riyadh corridor · last ping <span class="ratib-live-ping">2m</span> · geofence match</span>
                                        <span class="ratib-pill ratib-pill--muted">tracking OK</span>
                                    </div>
                                    <div class="ratib-dash-ledger" title="Finance connector · recent commits">
                                        <div class="ratib-dash-ledger__head ratib-mono-ops">Ledger tail · FIN-ME</div>
                                        <ul class="ratib-dash-ledger__rows ratib-mono-ops">
                                            <li>INV-20481 posted · VAT line · <span class="ratib-micro-delta ratib-micro-delta--ok">sync 312ms</span></li>
                                            <li>ACCR-TMP cleared · stage DEPLOY_OK</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
            <?php if ($ratibShowHomeVideoBand && (!function_exists('ratib_public_marketing_should_render_deep') || ratib_public_marketing_should_render_deep())): ?>
            <div class="ratib-hero__video-band video-section ratib-video ratib-video--hero" id="video">
                <div class="ratib-container">
                    <header class="ratib-hero__video-head ratib-section__head ratib-section__head--left">
                        <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.video.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <h2 class="ratib-section__title ratib-hero__video-title"><?php echo htmlspecialchars($ratibHome['home.video.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="video-caption"><?php echo htmlspecialchars($ratibHome['home.video.caption'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </header>
                    <?php if (!empty($ratibVideoSources)): ?>
                    <div class="ratib-cms-media-strip ratib-cms-media-strip--video" role="region" aria-label="<?php echo htmlspecialchars($ratibHome['home.video.title'] ?? 'Videos', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="ratib-cms-media-strip__track">
                            <?php foreach ($ratibVideoSources as $rvSrc): ?>
                            <div class="ratib-cms-media-strip__item ratib-cms-media-strip__item--video">
                                <div class="video-wrap ratib-cms-media-strip__video-wrap">
                                    <video controls preload="metadata" class="home-video-player ratib-cms-media-strip__video" playsinline>
                                        <source src="<?php echo htmlspecialchars((string) $rvSrc, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php elseif (!$videoExists && !$ratibVideoClearedInCms): ?>
                    <div class="ratib-video__shell">
                        <div class="video-wrap">
                            <div class="video-fallback-box">
                                <i class="fas fa-video-slash fa-3x mb-3"></i>
                                <p>Video not available on the server. Re-upload the MP4 in <strong>Public site content</strong> (video/pic section), or clear the slot to hide this block.</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($ratibProgSlotsOut) && (!function_exists('ratib_public_marketing_should_render_deep') || ratib_public_marketing_should_render_deep())): ?>
            <div class="ratib-hero__photo-strip ratib-hero__program-strip" id="program-previews">
                <div class="ratib-container">
                    <p class="ratib-hero__photo-eyebrow"><?php echo htmlspecialchars($ratibHome['home.program.strip_eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php
                    $ratibProgHint = trim((string) ($ratibHome['home.program.strip_hint'] ?? ''));
                    if ($ratibProgHint !== '') {
                        ?>
                    <p class="ratib-program-strip-hint"><?php echo htmlspecialchars($ratibProgHint, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php
                    }
                    ?>
                    <div class="ratib-cms-media-strip ratib-cms-media-strip--program ratib-program-marquee" data-ratib-program-marquee role="region" aria-label="<?php echo htmlspecialchars($ratibHome['home.program.strip_eyebrow'] ?? 'Program previews', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="ratib-program-marquee__shell">
                            <button type="button" class="ratib-program-marquee__scroll-btn ratib-program-marquee__scroll-btn--prev" data-ratib-program-marquee-scroll-prev aria-label="Scroll previews left"><span aria-hidden="true">&#8249;</span></button>
                            <div class="ratib-program-marquee__viewport">
                            <div class="ratib-cms-media-strip__track ratib-cms-media-strip__track--program ratib-program-marquee__track">
                                <?php for ($ratibMarqueePass = 0; $ratibMarqueePass < 2; $ratibMarqueePass++) { ?>
                                    <?php foreach ($ratibProgSlotsOut as $ratibProgSlot) {
                                        $ratibProgSrc = (string) $ratibProgSlot['src'];
                                        ?>
                            <div class="ratib-cms-media-strip__item ratib-cms-media-strip__item--program">
                                <figure class="ratib-hero__photo ratib-hero__photo--program" role="listitem">
                                    <button type="button" class="ratib-program-strip__thumb" data-ratib-gallery-open data-ratib-program-open data-full-src="<?php echo htmlspecialchars($ratibProgSrc, ENT_QUOTES, 'UTF-8'); ?>" data-caption="<?php echo htmlspecialchars((string) $ratibProgSlot['caption'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars('View larger: ' . ((string) $ratibProgSlot['caption'] !== '' ? (string) $ratibProgSlot['caption'] : (string) $ratibProgSlot['alt']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <img src="<?php echo htmlspecialchars($ratibProgSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $ratibProgSlot['alt'], ENT_QUOTES, 'UTF-8'); ?>" width="800" height="500" loading="lazy" decoding="async">
                                    </button>
                                    <figcaption><?php echo htmlspecialchars((string) $ratibProgSlot['caption'], ENT_QUOTES, 'UTF-8'); ?></figcaption>
                                </figure>
                            </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                            </div>
                            <button type="button" class="ratib-program-marquee__scroll-btn ratib-program-marquee__scroll-btn--next" data-ratib-program-marquee-scroll-next aria-label="Scroll previews right"><span aria-hidden="true">&#8250;</span></button>
                        </div>
                    </div>
                </div>
            </div>
                        <?php else: ?>
            <div class="ratib-hero__photo-strip ratib-hero__program-strip ratib-hero__program-strip--empty" id="program-previews">
                <div class="ratib-container">
                    <p class="ratib-hero__photo-eyebrow"><?php echo htmlspecialchars($ratibHome['home.program.strip_eyebrow'] ?? 'Program previews', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="ratib-program-strip-empty"><strong>No preview screenshots yet.</strong> In <strong>Control Panel → Public site content → Program preview strip</strong>, upload or choose an image for each slot and save. Then this row will show scroll arrows, the scrollbar, and clicking opens the viewer with Previous / Next.</p>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <?php ratib_marketing_expand_bar_render('home'); ?>

        <section class="ratib-section ratib-trust" id="platform">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.platform.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.platform.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="ratib-trust__grid">
                    <?php
                    $ratibTrustIcons = ['fa-user-shield', 'fa-clock-rotate-left', 'fa-lock', 'fa-stopwatch', 'fa-clipboard-check', 'fa-server'];
                    for ($ti = 1; $ti <= 6; $ti++) {
                        $ic = $ratibTrustIcons[$ti - 1] ?? 'fa-circle';
                        ?>
                    <article class="ratib-trust-card"><div class="ratib-trust-card__icon"><i class="fas <?php echo htmlspecialchars($ic, ENT_QUOTES, 'UTF-8'); ?>"></i></div><h3><?php echo htmlspecialchars($ratibHome['home.trust.' . $ti . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><p><?php echo htmlspecialchars($ratibHome['home.trust.' . $ti . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <?php if (!function_exists('ratib_public_marketing_should_render_deep') || ratib_public_marketing_should_render_deep()) { ?>
        <?php ratib_enterprise_trust_render_home($ratibHome, $baseUrl); ?>

        <?php if ($ratibOpProofAvailable) {
            ratib_operational_proof_render($baseUrl, [
                'eyebrow' => (string) ($ratibHome['home.op_proof.eyebrow'] ?? 'Operational proof'),
                'title' => (string) ($ratibHome['home.op_proof.title'] ?? ''),
                'sub' => (string) ($ratibHome['home.op_proof.sub'] ?? ''),
            ]);
        } ?>

        <section class="ratib-section ratib-domains-embed" id="domains" data-ratib-marketing-depth="deep">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow">Domains</p>
                    <h2 class="ratib-section__title">Find a domain</h2>
                    <p class="ratib-section__sub">Search availability and browse catalog offers when providers are active.</p>
                </header>
                <div class="ratib-home-domains-embed">
                    <iframe
                        class="ratib-home-domains-embed__frame"
                        title="Domain availability search and marketplace catalog"
                        src="<?php echo htmlspecialchars($ratibDomainsIframeSrc, ENT_QUOTES, 'UTF-8'); ?>"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                    ></iframe>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-how" id="how-it-works" data-ratib-marketing-depth="deep">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.how.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.how.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.how.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <ol class="ratib-how__steps" aria-label="Deployment sequence">
                    <?php for ($hi = 1; $hi <= 7; $hi++) {
                        $hn = str_pad((string) $hi, 2, '0', STR_PAD_LEFT); ?>
                    <li class="ratib-how__step"><span class="ratib-how__n" aria-hidden="true"><?php echo $hn; ?></span><strong class="ratib-how__title"><?php echo htmlspecialchars($ratibHome['home.how.step.' . $hi . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><span class="ratib-how__desc"><?php echo htmlspecialchars($ratibHome['home.how.step.' . $hi . '.desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></li>
                    <?php } ?>
                </ol>
            </div>
        </section>

        <section class="ratib-section" id="features">
            <div class="ratib-container">
                <header class="ratib-section__head ratib-section__head--left">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.features.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.features.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub ratib-section__sub--inline"><?php echo htmlspecialchars($ratibHome['home.features.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="ratib-feature-grid">
                    <?php
                    $ratibFeatureIcons = ['fa-gears', 'fa-id-badge', 'fa-shuffle', 'fa-location-dot', 'fa-globe', 'fa-file-signature', 'fa-coins', 'fa-receipt', 'fa-route', 'fa-bell', 'fa-chart-pie', 'fa-plug'];
                    for ($fi = 1; $fi <= 12; $fi++) {
                        $fic = $ratibFeatureIcons[$fi - 1] ?? 'fa-circle';
                        ?>
                    <article class="ratib-feature-card ratib-feature-card--tone<?php echo (int) $fi; ?>"><div class="ratib-feature-card__icon"><i class="fas <?php echo htmlspecialchars($fic, ENT_QUOTES, 'UTF-8'); ?>"></i></div><h3><?php echo htmlspecialchars($ratibHome['home.features.' . $fi . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><p><?php echo htmlspecialchars($ratibHome['home.features.' . $fi . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-pipeline-section" id="tracking" data-ratib-marketing-depth="deep">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.pipeline.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.pipeline.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.pipeline.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="ratib-pipeline" role="list">
                    <div class="ratib-pipeline__track" aria-hidden="true"></div>
                    <?php
                    $ratibPipeState = ['ratib-pipeline__item--complete', 'ratib-pipeline__item--complete', 'ratib-pipeline__item--active', '', '', '', '', ''];
                    for ($pi = 1; $pi <= 8; $pi++) {
                        $pcls = trim('ratib-pipeline__item ' . ($ratibPipeState[$pi - 1] ?? ''));
                        ?>
                    <div class="<?php echo htmlspecialchars($pcls, ENT_QUOTES, 'UTF-8'); ?>" role="listitem"><span class="ratib-pipeline__dot"></span><span class="ratib-pipeline__label"><?php echo htmlspecialchars($ratibHome['home.pipeline.step.' . $pi . '.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-pipeline__meta"><?php echo htmlspecialchars($ratibHome['home.pipeline.step.' . $pi . '.meta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-ai-section" id="solutions" data-ratib-marketing-depth="deep">
            <div class="ratib-container">
                <header class="ratib-section__head ratib-section__head--left">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.solutions.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.solutions.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.solutions.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="ratib-ai-grid ratib-use-grid">
                    <article class="ratib-ai-card ratib-ai-card--wide ratib-use-card ratib-use-card--wide">
                        <h3><?php echo htmlspecialchars($ratibHome['home.solutions.1.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($ratibHome['home.solutions.1.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="ratib-ai-visual ratib-use-visual">
                            <div class="ratib-ai-row"><span class="ratib-pill"><?php echo htmlspecialchars($ratibHome['home.solutions.1.demo_row.1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratibHome['home.solutions.1.demo_row.1b'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="ratib-ai-row"><span class="ratib-pill ratib-pill--accent"><?php echo htmlspecialchars($ratibHome['home.solutions.1.demo_row.2'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratibHome['home.solutions.1.demo_row.2b'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="ratib-ai-row"><span class="ratib-pill"><?php echo htmlspecialchars($ratibHome['home.solutions.1.demo_row.3'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratibHome['home.solutions.1.demo_row.3b'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </article>
                    <?php for ($si = 2; $si <= 6; $si++) { ?>
                    <article class="ratib-ai-card ratib-use-card">
                        <h3><?php echo htmlspecialchars($ratibHome['home.solutions.' . $si . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($ratibHome['home.solutions.' . $si . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-eco" id="agencies" data-ratib-marketing-depth="deep">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.agencies.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.agencies.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.agencies.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="ratib-eco__viz" aria-hidden="true">
                    <div class="ratib-eco__core">
                        <span class="ratib-eco__core-label"><?php echo htmlspecialchars($ratibHome['home.agencies.core.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="ratib-eco__core-sub"><?php echo htmlspecialchars($ratibHome['home.agencies.core.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="ratib-eco__spokes">
                        <?php for ($ei = 1; $ei <= 3; $ei++) { ?>
                        <div class="ratib-eco__spoke"><span><?php echo htmlspecialchars($ratibHome['home.agencies.spoke.' . $ei . '.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><small><?php echo htmlspecialchars($ratibHome['home.agencies.spoke.' . $ei . '.small'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></div>
                        <?php } ?>
                        <div class="ratib-eco__spoke ratib-eco__spoke--accent"><span><?php echo htmlspecialchars($ratibHome['home.agencies.spoke.4.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><small><?php echo htmlspecialchars($ratibHome['home.agencies.spoke.4.small'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-analytics" data-ratib-marketing-depth="deep">
            <div class="ratib-container">
                <header class="ratib-section__head ratib-section__head--left">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.analytics.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.analytics.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.analytics.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="ratib-analytics__grid">
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops"><?php echo htmlspecialchars($ratibHome['home.analytics.1.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratibHome['home.analytics.1.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="ratib-metric"><span class="ratib-metric__val"><?php echo htmlspecialchars($ratibHome['home.analytics.1.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-metric__chart ratib-metric__chart--line" aria-hidden="true"></span></div><span class="ratib-analytics__illus"><?php echo htmlspecialchars($ratibHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratibHome['home.analytics.1.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops"><?php echo htmlspecialchars($ratibHome['home.analytics.2.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratibHome['home.analytics.2.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="ratib-metric"><span class="ratib-metric__val"><?php echo htmlspecialchars($ratibHome['home.analytics.2.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-metric__chart ratib-metric__chart--bars" aria-hidden="true"></span></div><span class="ratib-analytics__illus"><?php echo htmlspecialchars($ratibHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratibHome['home.analytics.2.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops"><?php echo htmlspecialchars($ratibHome['home.analytics.3.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratibHome['home.analytics.3.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="ratib-metric"><span class="ratib-metric__val"><?php echo htmlspecialchars($ratibHome['home.analytics.3.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-metric__note"><?php echo htmlspecialchars($ratibHome['home.analytics.3.note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div><span class="ratib-analytics__illus"><?php echo htmlspecialchars($ratibHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratibHome['home.analytics.3.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops"><?php echo htmlspecialchars($ratibHome['home.analytics.4.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratibHome['home.analytics.4.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="ratib-metric"><span class="ratib-metric__val"><?php echo htmlspecialchars($ratibHome['home.analytics.4.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-metric__note"><?php echo htmlspecialchars($ratibHome['home.analytics.4.note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div><span class="ratib-analytics__illus"><?php echo htmlspecialchars($ratibHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratibHome['home.analytics.4.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-ops-visibility" id="operational" data-ratib-marketing-depth="deep">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.ops.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.ops.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.ops.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="ratib-ops__disclaimer ratib-mono-ops"><?php echo htmlspecialchars($ratibHome['home.ops.disclaimer'] ?? 'Illustrative interface · sample operational data only', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="ratib-ops__layout">
                    <div class="ratib-ops__panel ratib-ops__panel--preview">
                        <div class="ratib-ops__panel-bar">
                            <span class="ratib-mono-tag">sample.ops.panel</span>
                            <span class="ratib-pill ratib-pill--subtle">Sample operational data</span>
                        </div>
                        <div class="ratib-ops__preview-grid">
                            <div class="ratib-ops__mini">
                                <span class="ratib-ops__mini-label">Workflow health</span>
                                <span class="ratib-ops__mini-val ratib-ops__mini-val--ok">Healthy</span>
                                <span class="ratib-ops__mini-sub">0 breached SLA · <span class="ratib-live-sync-age">2m</span> since last reconcile</span>
                            </div>
                            <div class="ratib-ops__mini">
                                <span class="ratib-ops__mini-label">Throughput (24h)</span>
                                <span class="ratib-ops__mini-val">412</span>
                                <span class="ratib-ops__mini-sub">stage transitions committed</span>
                            </div>
                            <div class="ratib-ops__mini">
                                <span class="ratib-ops__mini-label">Automation</span>
                                <span class="ratib-ops__mini-val">3</span>
                                <span class="ratib-ops__mini-sub">workflows auto-resolved <span class="ratib-mono-tag">rolling 1h</span></span>
                            </div>
                            <div class="ratib-ops__mini">
                                <span class="ratib-ops__mini-label">Tracking stability</span>
                                <span class="ratib-ops__mini-val ratib-ops__mini-val--ok">Stable</span>
                                <span class="ratib-ops__mini-sub">Telemetry and checkpoint signals within variance</span>
                            </div>
                            <div class="ratib-ops__mini ratib-ops__mini--wide">
                                <span class="ratib-ops__mini-label">Document verification</span>
                                <span class="ratib-ops__mini-val">Queue depth 12</span>
                                <span class="ratib-ops__mini-sub">KYC · medical · embassy bundles · median review 14m</span>
                            </div>
                            <div class="ratib-ops__sparkline" aria-hidden="true">
                                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                            </div>
                        </div>
                    </div>
                    <div class="ratib-ops__events">
                        <div class="ratib-ops__events-head"><span class="ratib-mono-tag">event_log · tail</span><span class="ratib-pill ratib-pill--subtle">sample stream</span></div>
                        <ul class="ratib-ops__event-list">
                            <li><time class="ratib-ops__time ratib-live-clock" datetime="">--:--:--</time><span class="ratib-ops__evt">PIPELINE_MEDICAL_CLEAR · worker · shard A</span></li>
                            <li><time class="ratib-ops__time">—</time><span class="ratib-ops__evt">INV_EMIT · correlation id · FIN connector OK</span></li>
                            <li><time class="ratib-ops__time">—</time><span class="ratib-ops__evt">GEO_FENCE_MATCH · RUH corridor</span></li>
                            <li><time class="ratib-ops__time">—</time><span class="ratib-ops__evt">SLA_WATCH · no breach · policy CL-2024</span></li>
                        </ul>
                    </div>
                </div>
                <div class="ratib-trust-band">
                    <?php for ($oi = 1; $oi <= 6; $oi++) { ?>
                    <div class="ratib-trust-band__item"><span class="ratib-trust-band__k"><?php echo htmlspecialchars($ratibHome['home.ops.band.' . $oi . '.k'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-trust-band__v"><?php echo htmlspecialchars($ratibHome['home.ops.band.' . $oi . '.v'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-api-strip" id="api" data-ratib-marketing-depth="deep">
            <div class="ratib-container ratib-api-strip__inner">
                <div>
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.api.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-api-strip__title"><?php echo htmlspecialchars($ratibHome['home.api.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-api-strip__sub"><?php echo htmlspecialchars($ratibHome['home.api.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <a href="<?php echo htmlspecialchars(ratib_enterprise_mailto('RATEB — Contact Solutions Team'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-btn ratib-btn--outline"><?php echo htmlspecialchars($ratibHome['home.api.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </section>
        <?php } ?>

        <section class="pricing-section ratib-pricing-saas" id="programs">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.pricing.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.pricing.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.pricing.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="pricing-row pricing-row--three">
            <div class="price-card price-card-starter">
                <span class="card-badge card-badge--muted"><?php echo htmlspecialchars($ratibHome['home.pricing.starter.badge'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-plan"><?php echo htmlspecialchars($ratibHome['home.pricing.starter.plan'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="card-subtitle"><?php echo htmlspecialchars($ratibHome['home.pricing.starter.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <p class="card-price-saas"><?php echo htmlspecialchars($ratibHome['home.pricing.starter.price_line'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <?php foreach ($ratibPricingStarterLines as $ratibLine) { ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($ratibLine, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php } ?>
                </ul>
                <a href="<?php echo htmlspecialchars($ratibRegisterHref, ENT_QUOTES, 'UTF-8'); ?>" class="btn-register btn-register-starter js-open-register" data-register-plan="pro" data-register-amount="" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratibHome['home.pricing.starter.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
            <div class="price-card gold price-card--featured">
                <span class="card-badge"><?php echo htmlspecialchars($ratibHome['home.pricing.gold.badge'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-plan"><?php echo htmlspecialchars($ratibHome['home.pricing.gold.plan_word'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="card-plan-note">list $<?php echo number_format((float)$goldListPriceYear1, 0); ?></span></div>
                <div class="card-subtitle"><?php echo htmlspecialchars($ratibHome['home.pricing.gold.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="plan-year-wrap">
                    <div class="plan-year-buttons">
                        <button type="button" class="year-btn gold-year-btn year-btn-card year-btn-neutral" data-years="0" data-price="<?php echo (float)$goldTestPriceMonth; ?>">Monthly<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$goldListPriceMonth, 2); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceMonth, 2); ?></span></span></button>
                        <button type="button" class="year-btn gold-year-btn year-btn-card year-btn-gold-active active" data-years="1" data-price="<?php echo (float)$goldTestPriceYear1; ?>">1 Year<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
                <p class="card-price-old" id="goldOldPrice">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></p>
                <p class="card-price" id="goldPrice">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?> <span id="goldPriceLabel">for 1 year</span></p>
                <span class="card-discount"><?php echo htmlspecialchars($ratibHome['home.pricing.gold.discount_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <?php foreach ($ratibPricingGoldLines as $ratibLine) { ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($ratibLine, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php } ?>
                </ul>
                <a href="<?php echo htmlspecialchars($ratibRegisterHref, ENT_QUOTES, 'UTF-8'); ?>" id="goldRegisterBtn" class="btn-register js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratibHome['home.pricing.gold.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
            <div class="price-card platinum">
                <span class="card-badge"><?php echo htmlspecialchars($ratibHome['home.pricing.platinum.badge'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-plan"><?php echo htmlspecialchars($ratibHome['home.pricing.platinum.plan_word'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="card-plan-note">list $<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span></div>
                <div class="card-subtitle"><?php echo htmlspecialchars($ratibHome['home.pricing.platinum.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="plan-year-wrap">
                    <div class="plan-year-buttons">
                        <button type="button" class="year-btn platinum-year-btn year-btn-card year-btn-neutral" data-years="0" data-price="<?php echo (float)$platinumTestPriceMonth; ?>">Monthly<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$platinumListPriceMonth, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceMonth, 0); ?></span></span></button>
                        <button type="button" class="year-btn platinum-year-btn year-btn-card year-btn-platinum-active active" data-years="1" data-price="<?php echo (float)$platinumTestPriceYear1; ?>">1 Year<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
                <p class="card-price-old" id="platinumOldPrice">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></p>
                <p class="card-price" id="platinumPrice">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?> <span id="platinumPriceLabel">for 1 year</span></p>
                <span class="card-discount"><?php echo htmlspecialchars($ratibHome['home.pricing.platinum.discount_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <?php foreach ($ratibPricingPlatinumLines as $ratibLine) { ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($ratibLine, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php } ?>
                </ul>
                <a href="<?php echo htmlspecialchars($ratibRegisterHref, ENT_QUOTES, 'UTF-8'); ?>" id="platinumRegisterBtn" class="btn-register js-open-register" data-register-plan="platinum" data-register-amount="<?php echo (float)($plans['platinum']['amount'] ?? $platinumTestPriceYear1); ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratibHome['home.pricing.platinum.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </div>
            </div>
        </section>

        <section class="register-section<?php echo $openRegister ? '' : ' register-section-hidden'; ?> ratib-register-wrap" id="register">
        <div class="ratib-info">
            <h2><i class="fas fa-info-circle me-2 register-info-icon"></i><?php echo htmlspecialchars($ratibHome['home.register.info.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><?php echo htmlspecialchars($ratibHome['home.register.info.intro'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <ul class="checklist">
                <?php for ($ci = 1; $ci <= 7; $ci++) { ?>
                <li><i class="fas fa-check-circle"></i><span><?php echo strip_tags($ratibHome['home.register.check.' . $ci] ?? '', '<strong>'); ?></span></li>
                <?php } ?>
            </ul>
        </div>
        <div class="form-card">
            <h1><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($ratibHome['home.register.form.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="subtitle">Request <?php echo htmlspecialchars($planLabel); ?> plan access<?php if ($planAmount): ?> — $<?php echo number_format($planAmount); ?><?php if ($years !== null): ?><?php if ((int)$years === 0): ?> per month<?php elseif ((int)$years > 0): ?> for <?php echo (int)$years; ?> year<?php echo (int)$years > 1 ? 's' : ''; ?><?php else: ?> setup<?php endif; ?><?php else: ?> setup<?php endif; ?><?php endif; ?>. We will review and contact you.</p>
            <div class="mb-3">
                <label class="form-label">Choose Plan</label>
                <p class="small mb-2 form-plan-hint"><i class="fas fa-info-circle me-1"></i><?php echo strip_tags($ratibHome['home.register.form.plan_hint'] ?? '', '<strong>'); ?></p>
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <button type="button" class="btn plan-btn-form plan-btn-pro" data-plan="pro" data-amount="" data-years="1"><i class="fas fa-star me-1"></i> Pro</button>
                    <button type="button" class="btn plan-btn-form plan-btn-gold" data-plan="gold" data-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-years="1"><i class="fas fa-crown me-1"></i> Gold <span class="promo-old">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?></span></button>
                    <button type="button" class="btn plan-btn-form plan-btn-platinum" data-plan="platinum" data-amount="<?php echo (float)$platinumTestPriceYear1; ?>" data-years="1"><i class="fas fa-gem me-1"></i> Platinum <span class="promo-old">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?></span></button>
                </div>
                <div id="formYearButtonsWrap" class="mb-2 <?php echo ($plan !== 'pro' && $planAmount) ? '' : 'is-hidden'; ?>">
                    <label class="form-label form-duration-label">Duration</label>
                    <div class="d-flex gap-2 flex-wrap" id="formYearButtons">
                        <button type="button" class="form-year-btn" data-years="0" data-price-gold="<?php echo (float)$goldTestPriceMonth; ?>" data-price-platinum="<?php echo (float)$platinumTestPriceMonth; ?>">Monthly<br><span class="form-year-price"><span class="promo-old">$<?php echo number_format((float)$goldListPriceMonth, 2); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceMonth, 2); ?></span></span></button>
                        <button type="button" class="form-year-btn" data-years="1" data-price-gold="<?php echo (float)$goldTestPriceYear1; ?>" data-price-platinum="<?php echo (float)$platinumTestPriceYear1; ?>">1 yr<br><span class="form-year-price"><span class="promo-old">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
            </div>
            <div id="successMsg" class="alert alert-success success-msg mb-3 is-hidden" role="alert"><i class="fas fa-check-circle me-2"></i><span id="successText"></span></div>
            <form id="regForm" dir="ltr">
                <input type="hidden" name="plan" id="inputPlan" value="<?php echo htmlspecialchars($plan); ?>">
                <input type="hidden" name="plan_amount" id="inputPlanAmount" value="<?php echo $planAmount !== null ? (float)$planAmount : ''; ?>">
                <input type="hidden" name="years" id="inputYears" value="<?php echo $years !== null ? (int)$years : ''; ?>" data-allow-zero="1">
                <input type="hidden" name="payment_method" value="register">
                <div class="hp hp-field"><input type="text" id="hp" name="website_url" tabindex="-1" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">Agency Name *</label><input type="text" class="form-control" name="agency_name" required maxlength="255" placeholder="Your agency or company name"></div>
                <div class="mb-3"><label class="form-label">Agency ID</label><input type="text" class="form-control" name="agency_id" maxlength="64" placeholder="e.g. registration or license number"></div>
                <div class="mb-3">
                    <label class="form-label">Country *</label>
                    <select class="form-control<?php echo $ratibCountryIsLocked ? ' is-locked-country' : ''; ?>" name="<?php echo $ratibCountryIsLocked ? 'country_visible' : 'country'; ?>" id="countrySelect" required <?php echo $ratibCountryIsLocked ? 'disabled' : ''; ?>>
                        <option value="">-- Select Country --</option>
                        <?php foreach ($countries as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($ratibCountryIsLocked && $ratibLockedCountryName === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($ratibCountryIsLocked): ?>
                    <input type="hidden" name="country" value="<?php echo htmlspecialchars($ratibLockedCountryName, ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="small mt-2 mb-0 form-plan-hint"><i class="fas fa-lock me-1"></i>Country is set by your portal.</p>
                    <?php endif; ?>
                </div>
                <div class="mb-3 is-hidden" id="otherCountryWrap"><label class="form-label">Specify country</label><input type="text" class="form-control" name="country_other" id="countryOther" maxlength="255" placeholder="Enter country name"></div>
                <div class="mb-3"><label class="form-label">Contact Email *</label><input type="email" class="form-control" name="contact_email" required maxlength="255" placeholder="you@example.com"></div>
                <div class="mb-3"><label class="form-label">Contact Phone *</label><input type="text" class="form-control" name="contact_phone" required maxlength="64" placeholder="+1234567890"></div>
                <div class="mb-3"><label class="form-label">Desired Site URL (optional)</label><input type="url" class="form-control" name="desired_site_url" maxlength="512" placeholder="https://your-agency.out.ratib.sa"></div>
                <div class="mb-4"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" maxlength="2000" placeholder="Tell us about your agency or requirements..."></textarea></div>
                
                <!-- When Pro selected: hint to choose Gold/Platinum for pricing summary -->
                <div id="paymentBlockPlaceholder" class="mb-4 <?php echo ($plan !== 'pro' && $planAmount) ? 'is-hidden' : ''; ?>">
                    <div class="payment-placeholder-box">
                        <i class="fas fa-receipt me-2 payment-placeholder-icon"></i><?php echo strip_tags($ratibHome['home.register.payment_placeholder'] ?? '', '<strong>'); ?>
                    </div>
                </div>
                <!-- Payment block: always in DOM; shown only for Gold/Platinum (JS toggles visibility) -->
                <div id="paymentBlockWrap" class="payment-block-wrap mb-4 <?php echo ($plan !== 'pro' && $planAmount) ? '' : 'is-hidden'; ?>">
                    <!-- Payment Summary -->
                    <div class="mb-4 payment-summary-box payment-summary-panel">
                        <h4 class="payment-summary-title"><i class="fas fa-receipt me-2"></i><?php echo htmlspecialchars($ratibHome['home.register.payment_summary.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                        <?php
                        $__payableSubtotal = $planAmount ? (float)$planAmount : 0.0;
                        $__listSubtotal = $__payableSubtotal * 2;
                        $__discountAmount = $__listSubtotal - $__payableSubtotal;
                        ?>
                        <div class="payment-summary-row">
                            <span class="payment-summary-muted">List Price</span>
                            <span class="payment-summary-value" id="paymentSummaryListPrice">$<?php echo number_format($__listSubtotal, 2); ?></span>
                        </div>
                        <div class="payment-summary-row">
                            <span class="payment-summary-muted">Discount (50%)</span>
                            <span class="payment-summary-value" id="paymentSummaryDiscount">-$<?php echo number_format($__discountAmount, 2); ?></span>
                        </div>
                        <div class="payment-summary-row">
                            <span class="payment-summary-muted" id="paymentSummaryLabel"><?php echo htmlspecialchars($planLabel); ?> Plan (<?php echo ($years !== null && (int)$years === 0) ? 'monthly' : ((int)($years !== null ? $years : 1)) . ' year' . (((int)($years !== null ? $years : 1)) > 1 ? 's' : ''); ?>)</span>
                            <span class="payment-summary-value" id="paymentSummarySubtotal">$<?php echo $planAmount ? number_format((float)$planAmount, 2) : '0.00'; ?></span>
                        </div>
                        <div class="payment-summary-row">
                            <span class="payment-summary-muted">Tax (15%)</span>
                            <span class="payment-summary-value" id="paymentSummaryTax">$<?php echo $planAmount ? number_format($planAmount * 0.15, 2) : '0.00'; ?></span>
                        </div>
                        <div class="payment-summary-total-row">
                            <span>Total</span>
                            <span id="paymentSummaryTotal"><?php echo htmlspecialchars($ratibDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?> <?php echo $planAmount ? number_format(((float)$planAmount * 1.15 * (float)$ratibDisplayUsdRate), 2) : '0.00'; ?></span>
                        </div>
                        <?php
                        $__showNgeniusNote = ($plan !== 'pro' && $planAmount);
                        if ($__showNgeniusNote) {
                            $__usdTotal = (float) $planAmount * 1.15;
                            $__gatewayCurrency = strtoupper(trim((string) $ratibCheckoutCurrency));
                            if ($__gatewayCurrency === '') {
                                $__gatewayCurrency = 'SAR';
                            }
                            $__gatewayRate = ($__gatewayCurrency === 'SAR') ? (float) $ratibUsdToSar : 1.0;
                            if (!is_finite($__gatewayRate) || $__gatewayRate <= 0) {
                                $__gatewayRate = ($__gatewayCurrency === 'SAR') ? 3.75 : 1.0;
                            }
                            $__gatewayTotal = round($__usdTotal * $__gatewayRate, 2);
                            $__displayTotal = round($__usdTotal * $ratibDisplayUsdRate, 2);
                            ?>
                        <p class="small mb-0 mt-2 ratib-ngenius-currency-note">Card checkout is charged in <strong><?php echo htmlspecialchars($ratibDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?></strong>: <strong class="ratib-ngenius-sar-total"><?php echo htmlspecialchars($ratibDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format($__displayTotal, 2); ?></strong> <span class="ratib-ngenius-rate-note">(USD × <?php echo htmlspecialchars(number_format($ratibDisplayUsdRate, 2), ENT_QUOTES, 'UTF-8'); ?>)</span>.</p>
                        <?php if ($ratibDisplayCheckoutCurrency !== $__gatewayCurrency): ?>
                        <p class="small text-muted mb-0 mt-1 ratib-ngenius-currency-note">You will complete payment in <?php echo htmlspecialchars($__gatewayCurrency, ENT_QUOTES, 'UTF-8'); ?>.</p>
                        <?php endif; ?>
                        <?php } ?>
                    </div>
                    <p class="small mb-0 payment-summary-footnote"><i class="fas fa-file-invoice me-2 payment-summary-footnote-icon"></i><?php echo htmlspecialchars($ratibHome['home.register.payment_summary.footer'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                
                <button type="submit" class="btn btn-primary btn-submit" id="btnSubmit"><i class="fas fa-paper-plane me-2"></i><?php echo htmlspecialchars($ratibHome['home.register.submit'] ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
    </section>

        <section class="ratib-final-cta ratib-final-cta--enterprise" id="contact" aria-labelledby="ratib-final-cta-title">
            <div class="ratib-final-cta__bg" aria-hidden="true"></div>
            <div class="ratib-container ratib-final-cta__inner">
                <h2 id="ratib-final-cta-title" class="ratib-final-cta__title"><?php echo htmlspecialchars($ratibHome['home.final_cta.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="ratib-final-cta__sub"><?php echo htmlspecialchars($ratibHome['home.final_cta.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="ratib-final-cta__actions">
                    <a href="<?php echo htmlspecialchars(ratib_enterprise_mailto('RATEB — Request Enterprise Demo'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-btn ratib-btn--primary ratib-btn--lg"><?php echo htmlspecialchars($ratibHome['home.final_cta.btn_primary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="<?php echo htmlspecialchars($ratibWalkthroughHref, ENT_QUOTES, 'UTF-8'); ?>" class="ratib-btn ratib-btn--outline ratib-btn--lg"><?php echo htmlspecialchars($ratibHome['home.final_cta.btn_secondary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="<?php echo htmlspecialchars(ratib_enterprise_mailto('RATEB — Contact Solutions Team'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-btn ratib-btn--outline ratib-btn--lg"><?php echo htmlspecialchars($ratibHome['home.final_cta.btn_tertiary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="<?php echo htmlspecialchars(ratib_enterprise_mailto('RATEB — Request Security Brief'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-btn ratib-btn--ghost ratib-btn--lg"><?php echo htmlspecialchars($ratibHome['home.final_cta.btn_quaternary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </div>
        </section>
    

    <?php require __DIR__ . '/../includes/ratib-gallery-lightbox-markup.php'; ?>

</main>

<?php include __DIR__ . '/../includes/ratib-home-public-footer.php'; ?>

    <?php
    // EN: Pass server-side runtime values to JavaScript as JSON bootstrap.
    // AR: تمرير القيم المحسوبة من السيرفر إلى JavaScript بصيغة JSON.
    $ratibHomeBootstrap = [
        'checkoutCurrency' => $ratibCheckoutCurrency,
        'displayCheckoutCurrency' => $ratibDisplayCheckoutCurrency,
        'displayNgeniusLabel' => $ratibDisplayNgeniusLabel,
        'displayUsdRate' => (float) $ratibDisplayUsdRate,
        'usdToSar' => (float) $ratibUsdToSar,
        'openRegister' => $openRegister,
        'initialPlan' => $plan,
        'initialAmount' => $planAmount !== null ? (float) $planAmount : null,
        'initialYears' => $years !== null ? (int) $years : 1,
        'goldMonth' => (float) $goldTestPriceMonth,
        'goldYear1' => (float) $goldTestPriceYear1,
        'platinumMonth' => (float) $platinumTestPriceMonth,
        'platinumYear1' => (float) ($plans['platinum']['amount'] ?? $platinumTestPriceYear1),
    ];
    $ratibHomeJsPath = __DIR__ . '/../js/pages/home-page.js';
    clearstatcache(true, $ratibHomeJsPath);
    $ratibHomeJsTs = (int) (@filemtime($ratibHomeJsPath) ?: time());
    $ratibHomeJsQ = $ratibHomeJsTs . '-' . $ratibHomeUiRev . '-' . $ratibHomePhpMtime . $ratibHomeAssetExtraQ . '-c' . $ratibChromeBundleHash;

    $ratibGalleryLbJsPathHome = __DIR__ . '/../js/pages/ratib-gallery-lightbox.js';
    clearstatcache(true, $ratibGalleryLbJsPathHome);
    $ratibGalleryLbJsQHome = (int) (@filemtime($ratibGalleryLbJsPathHome) ?: time()) . '-' . $ratibHomeUiRev;
    ?>
    <script>
    (function () {
        var currentRev = <?php echo json_encode((string) $ratibCmsRev); ?>;
        if (!currentRev) return;
        function go(nextRev) {
            try {
                var u = new URL(window.location.href);
                if (u.searchParams.get('cms_rev') === String(nextRev)) return;
                u.searchParams.set('cms_rev', String(nextRev));
                window.location.replace(u.toString());
            } catch (e) {}
        }
        function check() {
            try {
                var latest = localStorage.getItem('ratib_cms_rev') || '';
                if (latest && latest !== currentRev) {
                    go(latest);
                }
            } catch (e) {}
        }
        window.addEventListener('storage', function (ev) {
            if (ev && ev.key === 'ratib_cms_rev' && ev.newValue) {
                go(ev.newValue);
            }
        });
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) check();
        });
        window.addEventListener('focus', check);
    })();
    </script>
    <script type="application/json" id="ratib-home-bootstrap"><?php echo json_encode($ratibHomeBootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <?php
    ?>
    <?php
    $ratibProfileGuardJsHome = __DIR__ . '/../js/pages/ratib-profile-nav-guard.js';
    clearstatcache(true, $ratibProfileGuardJsHome);
    $ratibProfileGuardQHome = (string) (int) (@filemtime($ratibProfileGuardJsHome) ?: time());
    ?>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-profile-nav-guard.js?v=<?php echo htmlspecialchars($ratibProfileGuardQHome, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-gallery-lightbox.js?v=<?php echo htmlspecialchars($ratibGalleryLbJsQHome, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/home-page.js?v=<?php echo htmlspecialchars($ratibHomeJsQ, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-mega-nav.js?v=<?php echo htmlspecialchars($ratibMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-marketing-focused.js?v=<?php echo htmlspecialchars($ratibMarketingFocusedJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>

    <!-- Chat Widget - Auto-answer support -->
    <button class="chat-widget-button" id="chatWidgetButton" aria-label="Open Chat"><i class="fas fa-comments"></i></button>
    <div class="chat-widget-container" id="chatWidgetContainer">
        <div class="chat-widget-header">
            <div class="chat-widget-header-info">
                <div class="chat-widget-header-avatar" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i></div>
                <div class="chat-widget-header-text"><h3><?php echo htmlspecialchars($ratibHome['home.chat.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><p class="online"><?php echo htmlspecialchars($ratibHome['home.chat.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></div>
            </div>
            <div class="chat-widget-header-actions">
                <button type="button" class="chat-widget-clear" id="chatWidgetClear" aria-label="Clear conversation" title="Clear assistant chat"><i class="fas fa-trash-alt"></i></button>
                <button type="button" class="chat-widget-close" id="chatWidgetClose" aria-label="Close Chat"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="chat-widget-messages" id="chatWidgetMessages"></div>
        <div class="chat-widget-input-area">
            <div class="chat-widget-input-wrapper">
                <textarea class="chat-widget-input" id="chatWidgetInput" placeholder="Type your message..." rows="1"></textarea>
                <button class="chat-widget-send" id="chatWidgetSend" aria-label="Send Message"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
    <script>window.RATIB_BASE_URL = <?php echo json_encode($baseUrl); ?>;</script>
    <?php $ratibPaymentJsVer = (int) (@filemtime(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'payment.js') ?: time()); ?>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/payment.js?v=<?php echo $ratibPaymentJsVer; ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/help-center/help-center-builtin-content.js"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/chat-widget.js"></script>
<?php
require_once __DIR__ . '/../includes/ratib-page-stamp.php';
ratib_emit_page_stamp('home');
?>
</body>
</html>


