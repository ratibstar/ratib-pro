<?php
/**
 * Public: Home / landing page — English, layout like rateb.sa reference.
 * EN: Prepares server-side values (plans/currency/assets), renders page sections, and bootstraps JS config.
 * AR: يجهّز قيم السيرفر (الخطط/العملة/الأصول)، ويعرض أقسام الصفحة، ثم يمرر إعدادات JavaScript.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/rateb-public-base-url.php';

// Legacy ?open=register on home → dedicated registration page
if (isset($_GET['open']) && trim((string) ($_GET['open'] ?? '')) === 'register') {
    $legacyPlan = trim((string) ($_GET['plan'] ?? 'gold')) ?: 'gold';
    $legacyYears = isset($_GET['years']) ? (int) $_GET['years'] : 1;
    $extra = $_GET;
    unset($extra['open']);
    if (!headers_sent()) {
        header('Location: ' . rateb_public_agency_register_url('', $legacyPlan, $legacyYears, $extra), true, 302);
        exit;
    }
}
/** Platform pills on this page use in-page #anchors (no full reload). */
$GLOBALS['rateb_public_nav_on_marketing_home'] = true;

// Fresh mega-nav HTML for JS when LiteSpeed serves cached home chrome (IA v3).
if (isset($_GET['rateb_mega_nav_fragment']) && (string) $_GET['rateb_mega_nav_fragment'] === '1') {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
        header('Pragma: no-cache');
        header('X-LiteSpeed-Cache-Control: no-cache', false);
    }
    require_once __DIR__ . '/../includes/rateb-mega-nav-render.php';
    $ratebNavResolve = __DIR__ . '/../includes/rateb-mega-nav-resolve.php';
    if (is_file($ratebNavResolve)) {
        require_once $ratebNavResolve;
    }
    rateb_mega_nav_render(rateb_public_site_base_url(), '');
    exit;
}

// LiteSpeed caches bare /pages/home.php with old Profile → new-tab HTML. Require ?v= build marker.
$ratebHomeSkipBuildBust = isset($_GET['rateb_deploy_probe'])
    || (isset($_GET['rateb_mega_nav_fragment']) && (string) $_GET['rateb_mega_nav_fragment'] === '1')
    || (
        isset($_GET['rateb_purge_lscache'], $_GET['key'])
        && (string) $_GET['rateb_purge_lscache'] === '1'
        && hash_equals('rateb-deploy-sync-2026', (string) $_GET['key'])
    );
if (!$ratebHomeSkipBuildBust) {
    $ratebIsRegisterCheckout = isset($_GET['open']) && trim((string) $_GET['open']) === 'register';
    $ratebBuildMarker = rateb_public_build_marker();
    $ratebReqBuildV = isset($_GET['v']) ? trim((string) $_GET['v']) : '';
    if ($ratebBuildMarker !== '' && !headers_sent() && !$ratebIsRegisterCheckout) {
        $needsCanonicalV = $ratebReqBuildV === ''
            || !rateb_public_build_marker_is_valid($ratebReqBuildV)
            || $ratebReqBuildV !== $ratebBuildMarker;
        if ($needsCanonicalV) {
            $qs = $_GET;
            $qs['v'] = $ratebBuildMarker;
            $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/home'), PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? $path : '/home';
            if (preg_match('#/pages/home$#i', $path)) {
                $path = '/home';
            }
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
    $ratebOpcacheBust = [
        __DIR__ . '/../includes/rateb-home-public-chrome-top.php',
        __DIR__ . '/../includes/rateb-home-public-nav-sync.php',
        __DIR__ . '/../includes/rateb-home-public-nav-bootstrap.php',
        __DIR__ . '/../includes/rateb-site-content-rebrand-sanitize.php',
        __DIR__ . '/../includes/site-content-home-data.php',
        __FILE__,
    ];
    foreach ($ratebOpcacheBust as $ratebOpcacheFile) {
        if (is_file($ratebOpcacheFile)) {
            opcache_invalidate($ratebOpcacheFile, true);
        }
    }
}

// Deploy probe: /pages/home.php?rateb_deploy_probe=1 (bundle about-enterprise-20260516-v9)
if (isset($_GET['rateb_deploy_probe']) && (string) $_GET['rateb_deploy_probe'] === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $probeRoot = dirname(__DIR__);
    $aboutPath = $probeRoot . '/pages/about.php';
    $chromePath = $probeRoot . '/includes/rateb-home-public-chrome-top.php';
    $buildPath = $probeRoot . '/public/rateb-build.txt';
    $homeSample = is_file(__FILE__) ? (string) file_get_contents(__FILE__, false, null, 0, 12000) : '';
    $chromeSample = is_file($chromePath) ? (string) file_get_contents($chromePath, false, null, 0, 16000) : '';
    echo "rateb-deploy-probe-via-home\n";
    echo 'chrome_onclick_disk=' . (stripos($chromeSample, 'data-rateb-go-profile') !== false ? 'yes' : 'no') . "\n";
    echo 'chrome_v13_disk=' . (stripos($chromeSample, 'brand-profile=v13-onclick') !== false ? 'yes' : 'no') . "\n";
    echo 'document_root=' . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
    echo 'probe_root=' . $probeRoot . "\n";
    echo 'git_marker=' . (is_file($buildPath) ? trim((string) file_get_contents($buildPath)) : 'missing') . "\n";
    $companyProfilePath = $probeRoot . '/pages/company-profile.php';
    echo 'about_php=' . (is_file($aboutPath) ? 'yes' : 'no') . "\n";
    echo 'company_profile_php=' . (is_file($companyProfilePath) ? 'yes' : 'no') . "\n";
    echo 'home_open_about=' . (str_contains($homeSample, "=== 'about'") ? 'yes' : 'no') . "\n";
    echo 'chrome_about_link=' . (str_contains($chromeSample, 'rateb-nav__link--about') ? 'yes' : 'no') . "\n";
    echo 'stamp_file=' . (is_file($probeRoot . '/.rateb-deploy-stamp') ? trim((string) file_get_contents($probeRoot . '/.rateb-deploy-stamp')) : 'missing') . "\n";
    exit;
}

// Company profile — /profile (canonical) or legacy ?open=profile|about on home.php
$ratebOpenParam = isset($_GET['open']) ? trim((string) $_GET['open']) : '';
if ($ratebOpenParam === 'about' || $ratebOpenParam === 'profile') {
    $ratebPath = $_SERVER['REQUEST_URI'] ?? '';
    $ratebBasePath = preg_replace('#/pages/[^?]*.*$#', '', $ratebPath) ?: '';
    $ratebScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $ratebHost = $_SERVER['HTTP_HOST'] ?? '';
    header('Location: ' . $ratebScheme . '://' . $ratebHost . $ratebBasePath . '/profile', true, 302);
    exit;
}

// Optional: /pages/home.php?rateb_purge_lscache=1&key=rateb-deploy-sync-2026 — ask LiteSpeed to purge this vhost cache.
if (
    isset($_GET['rateb_purge_lscache'], $_GET['key'])
    && (string) $_GET['rateb_purge_lscache'] === '1'
    && hash_equals('rateb-deploy-sync-2026', (string) $_GET['key'])
    && !headers_sent()
) {
    header('X-LiteSpeed-Purge: *');
    header('X-LiteSpeed-Cache-Control: no-cache');
}
// Always bust LiteSpeed page cache for marketing home (stale HTML had profile → new tab).
if (!headers_sent()) {
    header('X-LiteSpeed-Cache-Control: no-cache', false);
    header('X-LiteSpeed-Tag: rateb-home-' . date('YmdHi'), false);
}

// Prevent stale HTML caching (browser + reverse proxies + LiteSpeed).
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('X-RATEB-Home-Nav: v13-onclick');
    header('X-LiteSpeed-Cache-Control: no-cache', false);
    header('X-LiteSpeed-Tag: rateb-home-nocache', false);
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
if (!function_exists('rateb_ngenius_env')) {
    require_once __DIR__ . '/../config/env.php';
}
$ratebCheckoutCurrency = 'SAR';
$ratebUsdToSar = 3.75;
if (function_exists('rateb_ngenius_env')) {
    $ratebCheckoutCurrency = strtoupper(trim((string) rateb_ngenius_env('NGENIUS_CHECKOUT_CURRENCY', 'SAR'))) ?: 'SAR';
    $ratebUsdToSar = (float) rateb_ngenius_env('NGENIUS_USD_TO_SAR', '3.75');
}
if (!is_finite($ratebUsdToSar) || $ratebUsdToSar <= 0) {
    $ratebUsdToSar = 3.75;
}
$ratebDisplayCheckoutCurrency = $ratebCheckoutCurrency;
$ratebDisplayNgeniusLabel = ($ratebCheckoutCurrency === 'SAR') ? 'N-Genius KSA' : 'N-Genius';
$ratebDefaultUsdRates = [
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
    $ratebDisplayCheckoutCurrency = $countryCurrencyByCode[$countryCodeRaw];
} elseif ($countryNameRaw !== '' && isset($countryCurrencyByName[$countryNameRaw])) {
    $ratebDisplayCheckoutCurrency = $countryCurrencyByName[$countryNameRaw];
} elseif ($countrySlugRaw !== '' && isset($countryCurrencyBySlug[$countrySlugRaw])) {
    $ratebDisplayCheckoutCurrency = $countryCurrencyBySlug[$countrySlugRaw];
}
if ($ratebDisplayCheckoutCurrency !== 'SAR') {
    $ratebDisplayNgeniusLabel = 'N-Genius ' . $ratebDisplayCheckoutCurrency;
}
$ratebDisplayUsdRate = $ratebDefaultUsdRates[$ratebDisplayCheckoutCurrency] ?? 1.00;
$ratebDisplayRateKey = 'NGENIUS_USD_TO_' . preg_replace('/[^A-Z]/', '', $ratebDisplayCheckoutCurrency);
$ratebDisplayUsdRateEnv = (float) rateb_ngenius_env($ratebDisplayRateKey, (string) $ratebDisplayUsdRate);
if (is_finite($ratebDisplayUsdRateEnv) && $ratebDisplayUsdRateEnv > 0) {
    $ratebDisplayUsdRate = $ratebDisplayUsdRateEnv;
}
$ratebLockedCountryName = '';
if ($countryCodeRaw !== '' && isset($countryNameByCode[$countryCodeRaw])) {
    $ratebLockedCountryName = $countryNameByCode[$countryCodeRaw];
} elseif ($countrySlugRaw !== '' && isset($countryNameBySlug[$countrySlugRaw])) {
    $ratebLockedCountryName = $countryNameBySlug[$countrySlugRaw];
} elseif ($countryNameRaw !== '') {
    $countryNameTitle = ucwords(strtolower($countryNameRaw));
    if ($countryNameTitle !== '') {
        $ratebLockedCountryName = $countryNameTitle;
    }
}

require_once __DIR__ . '/../includes/rateb-public-base-url.php';
$baseUrl = rateb_public_site_base_url();
$ratebDomainsIframeSrc = $baseUrl . '/modules/infrastructure-marketplace/Views/marketplace/index.php?focus=domains&embed=1#infra-domain-search';

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
$ratebCountryIsLocked = ($ratebLockedCountryName !== '');
if ($ratebCountryIsLocked && !in_array($ratebLockedCountryName, $countries, true)) {
    array_unshift($countries, $ratebLockedCountryName);
}

require_once __DIR__ . '/../includes/site-content.php';
// Canonicalize homepage URL to current CMS revision so browser/CDN tabs don't stick to stale HTML across saves.
$ratebCmsRev = function_exists('rateb_site_content_revision_token') ? rateb_site_content_revision_token() : '';
if ($ratebCmsRev !== '') {
    $currentRev = isset($_GET['cms_rev']) ? (string) $_GET['cms_rev'] : '';
    if ($currentRev !== $ratebCmsRev && !headers_sent()) {
        $qs = $_GET;
        $qs['cms_rev'] = $ratebCmsRev;
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/home'), PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/home';
        if (preg_match('#/pages/home$#i', $path)) {
            $path = '/home';
        }
        $newUrl = $path . '?' . http_build_query($qs);
        header('Location: ' . $newUrl, true, 302);
        exit;
    }
}
require_once __DIR__ . '/../includes/rateb-home-public-nav-bootstrap.php';

$ratebEntTrustInclude = __DIR__ . '/../includes/rateb-enterprise-trust-home.php';
if (is_file($ratebEntTrustInclude)) {
    require_once $ratebEntTrustInclude;
} elseif (!function_exists('rateb_enterprise_trust_render_home')) {
    function rateb_enterprise_trust_render_home(array $ratebHome, string $baseUrl): void
    {
    }
    function rateb_enterprise_trust_render_hero_strip(array $ratebHome): void
    {
    }
}
if (!function_exists('rateb_enterprise_mailto')) {
    function rateb_enterprise_mailto(string $subject): string
    {
        return 'mailto:info@rateb.sa?subject=' . rawurlencode($subject);
    }
}

$ratebOpProofInclude = __DIR__ . '/../includes/rateb-operational-proof-render.php';
$ratebOpProofAvailable = is_file($ratebOpProofInclude);
if ($ratebOpProofAvailable) {
    require_once $ratebOpProofInclude;
} elseif (!function_exists('rateb_operational_proof_render')) {
    function rateb_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void
    {
    }
}

$ratebEntCssPath = __DIR__ . '/../css/pages/enterprise-trust-layer.css';
clearstatcache(true, $ratebEntCssPath);
$ratebEntCssQuery = (int) (@filemtime($ratebEntCssPath) ?: time()) . '-' . $ratebHomeUiRev . '-c' . $ratebChromeBundleHash;
$ratebEntCssAvailable = is_file($ratebEntCssPath);
$ratebOpCssPath = __DIR__ . '/../css/pages/operational-proof.css';
clearstatcache(true, $ratebOpCssPath);
$ratebOpCssQuery = (int) (@filemtime($ratebOpCssPath) ?: time()) . '-' . $ratebHomeUiRev . '-c' . $ratebChromeBundleHash;
$ratebOpCssAvailable = is_file($ratebOpCssPath);
$ratebMarketingFocusedCssPath = __DIR__ . '/../css/pages/home-marketing-focused.css';
clearstatcache(true, $ratebMarketingFocusedCssPath);
$ratebMarketingFocusedCssQuery = (int) (@filemtime($ratebMarketingFocusedCssPath) ?: time()) . '-' . $ratebHomeUiRev;
$ratebMarketingFocusedJsPath = __DIR__ . '/../js/pages/rateb-marketing-focused.js';
clearstatcache(true, $ratebMarketingFocusedJsPath);
$ratebMarketingFocusedJsQuery = (int) (@filemtime($ratebMarketingFocusedJsPath) ?: time()) . '-' . $ratebHomeUiRev;
$ratebSiteRoot = rtrim($baseUrl, '/');
$ratebHomeAnchor = static function (string $hash): string {
    return function_exists('rateb_public_marketing_home_anchor')
        ? rateb_public_marketing_home_anchor($hash)
        : ($hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#'));
};
$ratebRegisterHrefPro = rateb_public_agency_register_url($baseUrl, 'starter', 1);
$ratebRegisterHrefGold = rateb_public_agency_register_url($baseUrl, 'professional', 1);
$ratebRegisterHrefPlatinum = rateb_public_agency_register_url($baseUrl, 'enterprise', 1);
$ratebRegisterHref = $ratebRegisterHrefGold;
$ratebHeroTourHref = $ratebHomeAnchor('#video');
$ratebArchSectionsOk = is_file(__DIR__ . '/../includes/rateb-architecture-sections.php');
$ratebWalkthroughHref = $ratebArchSectionsOk
    ? $ratebSiteRoot . '/architecture/'
    : $ratebHomeAnchor('#enterprise-infrastructure');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <!-- rateb-cms-build: site-content=<?php echo (int) (@filemtime(__DIR__ . '/../includes/site-content.php') ?: 0); ?> home-data=<?php echo (int) (@filemtime(__DIR__ . '/../includes/site-content-home-data.php') ?: 0); ?> rebrand=<?php echo htmlspecialchars(trim((string) (@file_get_contents(__DIR__ . '/../public/rateb-build.txt') ?: '')), ENT_QUOTES, 'UTF-8'); ?> load=<?php echo (int) (@filemtime(__DIR__ . '/../config/env/load.php') ?: 0); ?> cms-src=<?php echo htmlspecialchars(function_exists('rateb_site_content_public_source_resolved') ? rateb_site_content_public_source_resolved() : '', ENT_QUOTES, 'UTF-8'); ?> phone-len=<?php echo (int) strlen((string) ($ratebHome['home.topbar.phone_display'] ?? '')); ?> dbfp=<?php echo htmlspecialchars($ratebDbFingerprint, ENT_QUOTES, 'UTF-8'); ?> ui-rev=<?php echo htmlspecialchars($ratebHomeUiRev, ENT_QUOTES, 'UTF-8'); ?> -->
    <meta charset="UTF-8">
    <?php
    require_once __DIR__ . '/../includes/rateb-profile-force-same-tab.php';
    rateb_emit_profile_force_same_tab($baseUrl);
    rateb_home_nav_emit_sync_guard_style();
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="rateb-home-ui-rev" content="<?php echo htmlspecialchars($ratebHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="rateb-chrome-bundle" content="<?php echo htmlspecialchars($ratebChromeBundleHash, ENT_QUOTES, 'UTF-8'); ?>">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title><?php echo htmlspecialchars($ratebHome['home.meta.page_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></title>
    <?php
    $ratebHomeMetaDesc = trim((string) ($ratebHome['home.hero.lead'] ?? 'Recruitment orchestration, workforce tracking, compliance, and finance-grade operations on one multi-tenant platform.'));
    $ratebHomeCanonical = rtrim($baseUrl, '/') . '/';
    ?>
    <meta name="description" content="<?php echo htmlspecialchars($ratebHomeMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars((string) ($ratebHome['home.meta.page_title'] ?? (function_exists('rateb_brand_full_title') ? rateb_brand_full_title() : 'RATEB — Recruitment Automation & Telemetry Enterprise Base')), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($ratebHomeMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($ratebHomeCanonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars((string) ($ratebHome['home.meta.page_title'] ?? (function_exists('rateb_brand_full_title') ? rateb_brand_full_title() : 'RATEB — Recruitment Automation & Telemetry Enterprise Base')), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($ratebHomeMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($ratebHomeCanonical, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    require_once __DIR__ . '/../includes/rateb-enterprise-schema.php';
    rateb_enterprise_schema_emit([
        rateb_enterprise_schema_organization($baseUrl),
        rateb_enterprise_schema_software_application($baseUrl),
    ]);
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/chat-widget.css">
    <?php rateb_home_public_nav_emit_stylesheets($baseUrl); ?>
    <?php if ($ratebEntCssAvailable) { ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/enterprise-trust-layer.css?v=<?php echo htmlspecialchars($ratebEntCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php } ?>
    <?php if ($ratebOpCssAvailable) { ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/operational-proof.css?v=<?php echo htmlspecialchars($ratebOpCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php } ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-marketing-focused.css?v=<?php echo htmlspecialchars($ratebMarketingFocusedCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (function_exists('rateb_marketing_emit_focused_rescue_css')) {
        rateb_marketing_emit_focused_rescue_css();
    } ?>
</head>
<body class="rateb-saas-home <?php echo htmlspecialchars(rateb_public_marketing_density_body_class(), ENT_QUOTES, 'UTF-8'); ?>" data-rateb-marketing-density="<?php echo htmlspecialchars(rateb_public_marketing_density(), ENT_QUOTES, 'UTF-8'); ?>" data-rateb-home-layout="video-hero-program-svgs" data-rateb-home-ui-rev="<?php echo htmlspecialchars($ratebHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>" data-rateb-deploy="<?php echo htmlspecialchars($ratebHomePhpMtime . '-' . $ratebHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>">

<?php
include __DIR__ . '/../includes/rateb-home-public-chrome-top.php';
?>

    <main class="rateb-main">
        <!-- RATEB public home layout: product tour video directly under hero grid; program preview SVGs below. Deploy fingerprint: search HTML for id="video" on hero band + data-rateb-home-layout on body. -->
        <section class="rateb-hero">
            <div class="rateb-container rateb-hero__grid">
                <div class="rateb-hero__copy">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.hero.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php
                    require_once __DIR__ . '/../includes/rateb-brand-full-title.php';
                    ?>
                    <h1 class="rateb-hero__title">
                        <?php rateb_render_brand_full_title(['variant' => 'hero', 'layout' => 'inline']); ?>
                    </h1>
                    <p class="rateb-hero__lead"><?php echo htmlspecialchars($ratebHome['home.hero.lead'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <ul class="rateb-hero__bullets">
                        <li><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($ratebHome['home.hero.bullet.1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
                        <li><i class="fas fa-handshake"></i> <?php echo htmlspecialchars($ratebHome['home.hero.bullet.2'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
                        <li><i class="fas fa-qrcode"></i> <?php echo htmlspecialchars($ratebHome['home.hero.bullet.3'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
                        <li><i class="fas fa-sitemap"></i> <?php echo htmlspecialchars($ratebHome['home.hero.bullet.4'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
                    </ul>
                    <?php if (!function_exists('rateb_public_marketing_should_render_deep') || rateb_public_marketing_should_render_deep()) {
                        rateb_enterprise_trust_render_hero_strip($ratebHome);
                    } ?>
                    <div class="rateb-hero__actions">
                        <a href="<?php echo htmlspecialchars(rateb_enterprise_mailto('RATEB — Request Enterprise Demo'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--primary rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.hero.cta_primary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                        <a href="<?php echo htmlspecialchars($ratebWalkthroughHref, ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--outline rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.hero.cta_secondary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    </div>
                </div>
                <?php if (!function_exists('rateb_public_marketing_should_render_deep') || rateb_public_marketing_should_render_deep()) { ?>
                <div class="rateb-hero__visual" aria-hidden="true" data-rateb-marketing-depth="deep">
                    <div class="rateb-dash">
                        <div class="rateb-dash__chrome">
                            <div class="rateb-dash__chrome-main">
                                <span class="rateb-dash__dot"></span><span class="rateb-dash__dot"></span><span class="rateb-dash__dot"></span>
                                <span class="rateb-dash__title">RATEB Command</span>
                                <span class="rateb-dash__live" title="Sample UI — illustrative"><span class="rateb-live-dot"></span> Sample</span>
                            </div>
                            <div class="rateb-dash__chrome-sub rateb-mono-ops">
                                <span class="rateb-env-tag">prod</span>
                                <span class="rateb-dash__sep">·</span>
                                <span class="rateb-dash__panel-id" title="Sample workspace UI">ws-demo-01</span>
                                <span class="rateb-dash__sep">·</span>
                                <span class="rateb-dash__sync"><span class="rateb-sync-label">Edge sync</span> <span class="rateb-live-sync-age">2m</span></span>
                                <span class="rateb-dash__sep">·</span>
                                <span title="UTC session clock">UTC <time class="rateb-live-clock" datetime=""></time></span>
                            </div>
                        </div>
                        <p class="rateb-dash__illus rateb-mono-ops">Sample workspace UI · illustrative metrics</p>
                        <div class="rateb-dash__body">
                            <div class="rateb-dash__sidebar">
                                <div class="rateb-dash__nav-item rateb-dash__nav-item--active">Pipeline</div>
                                <div class="rateb-dash__nav-item">Workforce</div>
                                <div class="rateb-dash__nav-item">Agencies</div>
                                <div class="rateb-dash__nav-item">Finance</div>
                            </div>
                            <div class="rateb-dash__main">
                                <div class="rateb-dash__row">
                                    <div class="rateb-kpi" title="Cohort in active lifecycle states">
                                        <span class="rateb-kpi__label">Active workers</span>
                                        <span class="rateb-kpi__value rateb-live-nudge" data-rateb-jitter="2847">2,847</span>
                                        <span class="rateb-kpi__delta rateb-kpi__delta--up">+18% WoW</span>
                                    </div>
                                    <div class="rateb-kpi" title="Committed lifecycle transitions · rolling 24h">
                                        <span class="rateb-kpi__label">Stage commits (24h)</span>
                                        <span class="rateb-kpi__value">412</span>
                                        <span class="rateb-kpi__delta">event_log · shard A</span>
                                    </div>
                                    <div class="rateb-kpi" title="Stage SLAs met vs policy clock">
                                        <span class="rateb-kpi__label">SLA adherence</span>
                                        <span class="rateb-kpi__value rateb-live-nudge" data-rateb-jitter-pct="94.6">94.6%</span>
                                        <span class="rateb-kpi__delta rateb-kpi__delta--up">within policy</span>
                                    </div>
                                </div>
                                <div class="rateb-dash__signals">
                                    <span class="rateb-signal rateb-signal--ok" title="No breached stage clocks in this shard"><i class="fas fa-shield-halved" aria-hidden="true"></i> SLA policy OK</span>
                                    <span class="rateb-signal rateb-signal--ok" title="Document verification queue depth within SLO"><i class="fas fa-file-circle-check" aria-hidden="true"></i> KYC queue stable</span>
                                    <span class="rateb-signal rateb-signal--ok" title="Orchestrator round-trip p95"><i class="fas fa-gauge-high" aria-hidden="true"></i> ORCH p95 238ms</span>
                                </div>
                                <div class="rateb-dash__toolbar rateb-mono-ops">
                                    <span>Reconcile <span class="rateb-live-sync-age">2m</span></span>
                                    <span class="rateb-dash__sep">·</span>
                                    <span>Viewer <strong class="rateb-dash__strong">ops.supervisor</strong></span>
                                    <span class="rateb-dash__sep">·</span>
                                    <span class="rateb-pill rateb-pill--subtle">policy CL-2024-ME</span>
                                </div>
                                <p class="rateb-dash__context rateb-mono-ops">Pinned file <strong>WKR-ME-44821</strong> · corr <span class="rateb-dash__corr">ae7f9c2</span> · Medical clearance window</p>
                                <div class="rateb-dash__workspace">
                                    <div class="rateb-dash__panel rateb-dash__panel--table">
                                        <div class="rateb-dash__panel-head">
                                            <span>Workforce records</span>
                                            <span class="rateb-pill rateb-pill--subtle">tenant ACME · shard A</span>
                                        </div>
                                        <div class="rateb-dash-table-scroll">
                                            <table class="rateb-dash-table">
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
                                                        <td><span class="rateb-dash-tag rateb-dash-tag--warn">Medical</span></td>
                                                        <td class="rateb-dash-num">38h left</td>
                                                        <td>n.alharbi</td>
                                                        <td class="rateb-dash-num">14:06</td>
                                                    </tr>
                                                    <tr>
                                                        <td>WKR-UG-90213</td>
                                                        <td><span class="rateb-dash-tag rateb-dash-tag--idle">Embassy</span></td>
                                                        <td class="rateb-dash-num">queued</td>
                                                        <td>queue</td>
                                                        <td class="rateb-dash-num">13:58</td>
                                                    </tr>
                                                    <tr>
                                                        <td>WKR-BD-77104</td>
                                                        <td><span class="rateb-dash-tag rateb-dash-tag--ok">Visa</span></td>
                                                        <td class="rateb-dash-num">OK</td>
                                                        <td>s.rehman</td>
                                                        <td class="rateb-dash-num">13:41</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="rateb-dash__sidecol">
                                        <div class="rateb-dash__panel rateb-dash__panel--stack">
                                            <div class="rateb-dash__panel-head">
                                                <span>Verification queue</span>
                                                <span class="rateb-pill">depth 4</span>
                                            </div>
                                            <ul class="rateb-dash-qlist">
                                                <li><span class="rateb-q-type">Med bundle</span><span class="rateb-q-meta">review · est 11m</span></li>
                                                <li><span class="rateb-q-type">Emb. appointment</span><span class="rateb-q-meta">await scan</span></li>
                                                <li><span class="rateb-q-type">Police cert</span><span class="rateb-q-meta">OCR hold</span></li>
                                            </ul>
                                        </div>
                                        <div class="rateb-dash__panel rateb-dash__panel--stack">
                                            <div class="rateb-dash__panel-head">
                                                <span>Operational alerts</span>
                                                <span class="rateb-pill rateb-pill--subtle">last 1h</span>
                                            </div>
                                            <ul class="rateb-dash-alerts">
                                                <li class="rateb-dash-alerts__item rateb-dash-alerts__item--info"><span class="rateb-dash-alerts__sev">INFO</span> FIN webhook ACK · 312ms</li>
                                                <li class="rateb-dash-alerts__item rateb-dash-alerts__item--warn"><span class="rateb-dash-alerts__sev">WARN</span> Embassy slot drift · agency B</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="rateb-dash__footer">
                                    <div class="rateb-mapstrip">
                                        <i class="fas fa-location-crosshairs" aria-hidden="true"></i>
                                        <span>GPS · Riyadh corridor · last ping <span class="rateb-live-ping">2m</span> · geofence match</span>
                                        <span class="rateb-pill rateb-pill--muted">tracking OK</span>
                                    </div>
                                    <div class="rateb-dash-ledger" title="Finance connector · recent commits">
                                        <div class="rateb-dash-ledger__head rateb-mono-ops">Ledger tail · FIN-ME</div>
                                        <ul class="rateb-dash-ledger__rows rateb-mono-ops">
                                            <li>INV-20481 posted · VAT line · <span class="rateb-micro-delta rateb-micro-delta--ok">sync 312ms</span></li>
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
            <?php if ($ratebShowHomeVideoBand && (!function_exists('rateb_public_marketing_should_render_deep') || rateb_public_marketing_should_render_deep())): ?>
            <div class="rateb-hero__video-band video-section rateb-video rateb-video--hero" id="video">
                <div class="rateb-container">
                    <header class="rateb-hero__video-head rateb-section__head rateb-section__head--left">
                        <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.video.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <h2 class="rateb-section__title rateb-hero__video-title"><?php echo htmlspecialchars($ratebHome['home.video.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="video-caption"><?php echo htmlspecialchars($ratebHome['home.video.caption'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </header>
                    <?php if (!empty($ratebVideoSources)): ?>
                    <div class="rateb-cms-media-strip rateb-cms-media-strip--video" role="region" aria-label="<?php echo htmlspecialchars($ratebHome['home.video.title'] ?? 'Videos', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="rateb-cms-media-strip__track">
                            <?php foreach ($ratebVideoSources as $rvSlot): ?>
                            <?php
                            $rvSrc = is_array($rvSlot) ? (string) ($rvSlot['url'] ?? '') : (string) $rvSlot;
                            $rvIsImage = is_array($rvSlot) && !empty($rvSlot['is_image']);
                            ?>
                            <div class="rateb-cms-media-strip__item rateb-cms-media-strip__item--video">
                                <div class="video-wrap rateb-cms-media-strip__video-wrap">
                                    <?php if ($rvIsImage): ?>
                                    <img src="<?php echo htmlspecialchars($rvSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="home-video-player rateb-cms-media-strip__video rateb-cms-media-strip__still" loading="lazy" decoding="async">
                                    <?php else: ?>
                                    <video controls preload="metadata" class="home-video-player rateb-cms-media-strip__video" playsinline>
                                        <source src="<?php echo htmlspecialchars($rvSrc, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php elseif (!$videoExists && !$ratebVideoClearedInCms): ?>
                    <div class="rateb-video__shell">
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
            <?php if (!empty($ratebProgSlotsOut) && (!function_exists('rateb_public_marketing_should_render_deep') || rateb_public_marketing_should_render_deep())): ?>
            <div class="rateb-hero__photo-strip rateb-hero__program-strip" id="program-previews">
                <div class="rateb-container">
                    <p class="rateb-hero__photo-eyebrow"><?php echo htmlspecialchars($ratebHome['home.program.strip_eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php
                    $ratebProgHint = trim((string) ($ratebHome['home.program.strip_hint'] ?? ''));
                    if ($ratebProgHint !== '') {
                        ?>
                    <p class="rateb-program-strip-hint"><?php echo htmlspecialchars($ratebProgHint, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php
                    }
                    ?>
                    <div class="rateb-cms-media-strip rateb-cms-media-strip--program rateb-program-marquee" data-rateb-program-marquee role="region" aria-label="<?php echo htmlspecialchars($ratebHome['home.program.strip_eyebrow'] ?? 'Program previews', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="rateb-program-marquee__shell">
                            <button type="button" class="rateb-program-marquee__scroll-btn rateb-program-marquee__scroll-btn--prev" data-rateb-program-marquee-scroll-prev aria-label="Scroll previews left"><span aria-hidden="true">&#8249;</span></button>
                            <div class="rateb-program-marquee__viewport">
                            <div class="rateb-cms-media-strip__track rateb-cms-media-strip__track--program rateb-program-marquee__track">
                                <?php for ($ratebMarqueePass = 0; $ratebMarqueePass < 2; $ratebMarqueePass++) { ?>
                                    <?php foreach ($ratebProgSlotsOut as $ratebProgSlot) {
                                        $ratebProgSrc = (string) $ratebProgSlot['src'];
                                        ?>
                            <div class="rateb-cms-media-strip__item rateb-cms-media-strip__item--program">
                                <figure class="rateb-hero__photo rateb-hero__photo--program" role="listitem">
                                    <button type="button" class="rateb-program-strip__thumb" data-rateb-gallery-open data-rateb-program-open data-full-src="<?php echo htmlspecialchars($ratebProgSrc, ENT_QUOTES, 'UTF-8'); ?>" data-caption="<?php echo htmlspecialchars((string) $ratebProgSlot['caption'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars('View larger: ' . ((string) $ratebProgSlot['caption'] !== '' ? (string) $ratebProgSlot['caption'] : (string) $ratebProgSlot['alt']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <img src="<?php echo htmlspecialchars($ratebProgSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $ratebProgSlot['alt'], ENT_QUOTES, 'UTF-8'); ?>" width="800" height="500" loading="lazy" decoding="async">
                                    </button>
                                    <figcaption><?php echo htmlspecialchars((string) $ratebProgSlot['caption'], ENT_QUOTES, 'UTF-8'); ?></figcaption>
                                </figure>
                            </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                            </div>
                            <button type="button" class="rateb-program-marquee__scroll-btn rateb-program-marquee__scroll-btn--next" data-rateb-program-marquee-scroll-next aria-label="Scroll previews right"><span aria-hidden="true">&#8250;</span></button>
                        </div>
                    </div>
                </div>
            </div>
                        <?php else: ?>
            <div class="rateb-hero__photo-strip rateb-hero__program-strip rateb-hero__program-strip--empty" id="program-previews">
                <div class="rateb-container">
                    <p class="rateb-hero__photo-eyebrow"><?php echo htmlspecialchars($ratebHome['home.program.strip_eyebrow'] ?? 'Program previews', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="rateb-program-strip-empty"><strong>No preview screenshots yet.</strong> In <strong>Control Panel → Public site content → Program preview strip</strong>, upload or choose an image for each slot and save. Then this row will show scroll arrows, the scrollbar, and clicking opens the viewer with Previous / Next.</p>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <?php rateb_marketing_expand_bar_render('home'); ?>

        <section class="rateb-section rateb-trust" id="platform">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.platform.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.platform.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-trust__grid">
                    <?php
                    $ratebTrustIcons = ['fa-user-shield', 'fa-clock-rotate-left', 'fa-lock', 'fa-stopwatch', 'fa-clipboard-check', 'fa-server'];
                    for ($ti = 1; $ti <= 6; $ti++) {
                        $ic = $ratebTrustIcons[$ti - 1] ?? 'fa-circle';
                        ?>
                    <article class="rateb-trust-card"><div class="rateb-trust-card__icon"><i class="fas <?php echo htmlspecialchars($ic, ENT_QUOTES, 'UTF-8'); ?>"></i></div><h3><?php echo htmlspecialchars($ratebHome['home.trust.' . $ti . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><p><?php echo htmlspecialchars($ratebHome['home.trust.' . $ti . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <?php if (!function_exists('rateb_public_marketing_should_render_deep') || rateb_public_marketing_should_render_deep()) { ?>
        <?php rateb_enterprise_trust_render_home($ratebHome, $baseUrl); ?>

        <?php if ($ratebOpProofAvailable) {
            rateb_operational_proof_render($baseUrl, [
                'eyebrow' => (string) ($ratebHome['home.op_proof.eyebrow'] ?? 'Operational proof'),
                'title' => (string) ($ratebHome['home.op_proof.title'] ?? ''),
                'sub' => (string) ($ratebHome['home.op_proof.sub'] ?? ''),
            ]);
        } ?>

        <section class="rateb-section rateb-domains-embed" id="domains" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow">Domains</p>
                    <h2 class="rateb-section__title">Find a domain</h2>
                    <p class="rateb-section__sub">Search availability and browse catalog offers when providers are active.</p>
                </header>
                <div class="rateb-home-domains-embed">
                    <iframe
                        class="rateb-home-domains-embed__frame"
                        title="Domain availability search and marketplace catalog"
                        src="<?php echo htmlspecialchars($ratebDomainsIframeSrc, ENT_QUOTES, 'UTF-8'); ?>"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                    ></iframe>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-how" id="how-it-works" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.how.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.how.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.how.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <ol class="rateb-how__steps" aria-label="Deployment sequence">
                    <?php for ($hi = 1; $hi <= 7; $hi++) {
                        $hn = str_pad((string) $hi, 2, '0', STR_PAD_LEFT); ?>
                    <li class="rateb-how__step"><span class="rateb-how__n" aria-hidden="true"><?php echo $hn; ?></span><strong class="rateb-how__title"><?php echo htmlspecialchars($ratebHome['home.how.step.' . $hi . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><span class="rateb-how__desc"><?php echo htmlspecialchars($ratebHome['home.how.step.' . $hi . '.desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></li>
                    <?php } ?>
                </ol>
            </div>
        </section>

        <section class="rateb-section" id="features">
            <div class="rateb-container">
                <header class="rateb-section__head rateb-section__head--left">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.features.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.features.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub rateb-section__sub--inline"><?php echo htmlspecialchars($ratebHome['home.features.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-feature-grid">
                    <?php
                    $ratebFeatureIcons = ['fa-gears', 'fa-id-badge', 'fa-shuffle', 'fa-location-dot', 'fa-globe', 'fa-file-signature', 'fa-coins', 'fa-receipt', 'fa-route', 'fa-bell', 'fa-chart-pie', 'fa-plug'];
                    for ($fi = 1; $fi <= 12; $fi++) {
                        $fic = $ratebFeatureIcons[$fi - 1] ?? 'fa-circle';
                        ?>
                    <article class="rateb-feature-card rateb-feature-card--tone<?php echo (int) $fi; ?>"><div class="rateb-feature-card__icon"><i class="fas <?php echo htmlspecialchars($fic, ENT_QUOTES, 'UTF-8'); ?>"></i></div><h3><?php echo htmlspecialchars($ratebHome['home.features.' . $fi . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><p><?php echo htmlspecialchars($ratebHome['home.features.' . $fi . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-pipeline-section" id="tracking" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.pipeline.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.pipeline.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.pipeline.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-pipeline" role="list">
                    <div class="rateb-pipeline__track" aria-hidden="true"></div>
                    <?php
                    $ratebPipeState = ['rateb-pipeline__item--complete', 'rateb-pipeline__item--complete', 'rateb-pipeline__item--active', '', '', '', '', ''];
                    for ($pi = 1; $pi <= 8; $pi++) {
                        $pcls = trim('rateb-pipeline__item ' . ($ratebPipeState[$pi - 1] ?? ''));
                        ?>
                    <div class="<?php echo htmlspecialchars($pcls, ENT_QUOTES, 'UTF-8'); ?>" role="listitem"><span class="rateb-pipeline__dot"></span><span class="rateb-pipeline__label"><?php echo htmlspecialchars($ratebHome['home.pipeline.step.' . $pi . '.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-pipeline__meta"><?php echo htmlspecialchars($ratebHome['home.pipeline.step.' . $pi . '.meta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-ai-section" id="solutions" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head rateb-section__head--left">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.solutions.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.solutions.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.solutions.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-ai-grid rateb-use-grid">
                    <article class="rateb-ai-card rateb-ai-card--wide rateb-use-card rateb-use-card--wide">
                        <h3><?php echo htmlspecialchars($ratebHome['home.solutions.1.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($ratebHome['home.solutions.1.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="rateb-ai-visual rateb-use-visual">
                            <div class="rateb-ai-row"><span class="rateb-pill"><?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.1b'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="rateb-ai-row"><span class="rateb-pill rateb-pill--accent"><?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.2'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.2b'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="rateb-ai-row"><span class="rateb-pill"><?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.3'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.3b'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </article>
                    <?php for ($si = 2; $si <= 6; $si++) { ?>
                    <article class="rateb-ai-card rateb-use-card">
                        <h3><?php echo htmlspecialchars($ratebHome['home.solutions.' . $si . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($ratebHome['home.solutions.' . $si . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-eco" id="agencies" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.agencies.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.agencies.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.agencies.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-eco__viz" aria-hidden="true">
                    <div class="rateb-eco__core">
                        <span class="rateb-eco__core-label"><?php echo htmlspecialchars($ratebHome['home.agencies.core.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="rateb-eco__core-sub"><?php echo htmlspecialchars($ratebHome['home.agencies.core.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="rateb-eco__spokes">
                        <?php for ($ei = 1; $ei <= 3; $ei++) { ?>
                        <div class="rateb-eco__spoke"><span><?php echo htmlspecialchars($ratebHome['home.agencies.spoke.' . $ei . '.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><small><?php echo htmlspecialchars($ratebHome['home.agencies.spoke.' . $ei . '.small'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></div>
                        <?php } ?>
                        <div class="rateb-eco__spoke rateb-eco__spoke--accent"><span><?php echo htmlspecialchars($ratebHome['home.agencies.spoke.4.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><small><?php echo htmlspecialchars($ratebHome['home.agencies.spoke.4.small'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-analytics" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head rateb-section__head--left">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.analytics.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.analytics.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.analytics.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (trim((string) ($ratebHome['home.analytics.sample_tag'] ?? '')) !== '') { ?>
                    <p class="rateb-sample-data-tag"><?php echo htmlspecialchars($ratebHome['home.analytics.sample_tag'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php } ?>
                </header>
                <div class="rateb-analytics__grid">
                    <article class="rateb-analytics-card"><p class="rateb-analytics-card__stamp rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.analytics.1.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratebHome['home.analytics.1.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="rateb-metric"><span class="rateb-metric__val"><?php echo htmlspecialchars($ratebHome['home.analytics.1.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-metric__chart rateb-metric__chart--line" aria-hidden="true"></span></div><span class="rateb-analytics__illus"><?php echo htmlspecialchars($ratebHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratebHome['home.analytics.1.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="rateb-analytics-card"><p class="rateb-analytics-card__stamp rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.analytics.2.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratebHome['home.analytics.2.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="rateb-metric"><span class="rateb-metric__val"><?php echo htmlspecialchars($ratebHome['home.analytics.2.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-metric__chart rateb-metric__chart--bars" aria-hidden="true"></span></div><span class="rateb-analytics__illus"><?php echo htmlspecialchars($ratebHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratebHome['home.analytics.2.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="rateb-analytics-card"><p class="rateb-analytics-card__stamp rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.analytics.3.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratebHome['home.analytics.3.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="rateb-metric"><span class="rateb-metric__val"><?php echo htmlspecialchars($ratebHome['home.analytics.3.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-metric__note"><?php echo htmlspecialchars($ratebHome['home.analytics.3.note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div><span class="rateb-analytics__illus"><?php echo htmlspecialchars($ratebHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratebHome['home.analytics.3.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="rateb-analytics-card"><p class="rateb-analytics-card__stamp rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.analytics.4.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratebHome['home.analytics.4.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="rateb-metric"><span class="rateb-metric__val"><?php echo htmlspecialchars($ratebHome['home.analytics.4.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-metric__note"><?php echo htmlspecialchars($ratebHome['home.analytics.4.note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div><span class="rateb-analytics__illus"><?php echo htmlspecialchars($ratebHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratebHome['home.analytics.4.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-ops-visibility" id="operational" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.ops.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.ops.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.ops.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="rateb-ops__disclaimer rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.ops.disclaimer'] ?? 'Illustrative interface · sample operational data only', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-ops__layout">
                    <div class="rateb-ops__panel rateb-ops__panel--preview">
                        <div class="rateb-ops__panel-bar">
                            <span class="rateb-mono-tag">sample.ops.panel</span>
                            <span class="rateb-pill rateb-pill--subtle">Sample operational data</span>
                        </div>
                        <div class="rateb-ops__preview-grid">
                            <div class="rateb-ops__mini">
                                <span class="rateb-ops__mini-label">Workflow health</span>
                                <span class="rateb-ops__mini-val rateb-ops__mini-val--ok">Healthy</span>
                                <span class="rateb-ops__mini-sub">0 breached SLA · <span class="rateb-live-sync-age">2m</span> since last reconcile</span>
                            </div>
                            <div class="rateb-ops__mini">
                                <span class="rateb-ops__mini-label">Throughput (24h)</span>
                                <span class="rateb-ops__mini-val">412</span>
                                <span class="rateb-ops__mini-sub">stage transitions committed</span>
                            </div>
                            <div class="rateb-ops__mini">
                                <span class="rateb-ops__mini-label">Automation</span>
                                <span class="rateb-ops__mini-val">3</span>
                                <span class="rateb-ops__mini-sub">workflows auto-resolved <span class="rateb-mono-tag">rolling 1h</span></span>
                            </div>
                            <div class="rateb-ops__mini">
                                <span class="rateb-ops__mini-label">Tracking stability</span>
                                <span class="rateb-ops__mini-val rateb-ops__mini-val--ok">Stable</span>
                                <span class="rateb-ops__mini-sub">Tracking and checkpoint signals within variance</span>
                            </div>
                            <div class="rateb-ops__mini rateb-ops__mini--wide">
                                <span class="rateb-ops__mini-label">Document verification</span>
                                <span class="rateb-ops__mini-val">Queue depth 12</span>
                                <span class="rateb-ops__mini-sub">KYC · medical · embassy bundles · median review 14m</span>
                            </div>
                            <div class="rateb-ops__sparkline" aria-hidden="true">
                                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                            </div>
                        </div>
                    </div>
                    <div class="rateb-ops__events">
                        <div class="rateb-ops__events-head"><span class="rateb-mono-tag">event_log · tail</span><span class="rateb-pill rateb-pill--subtle">sample stream</span></div>
                        <ul class="rateb-ops__event-list">
                            <li><time class="rateb-ops__time rateb-live-clock" datetime="">--:--:--</time><span class="rateb-ops__evt">PIPELINE_MEDICAL_CLEAR · worker · shard A</span></li>
                            <li><time class="rateb-ops__time">—</time><span class="rateb-ops__evt">INV_EMIT · correlation id · FIN connector OK</span></li>
                            <li><time class="rateb-ops__time">—</time><span class="rateb-ops__evt">GEO_FENCE_MATCH · RUH corridor</span></li>
                            <li><time class="rateb-ops__time">—</time><span class="rateb-ops__evt">SLA_WATCH · no breach · policy CL-2024</span></li>
                        </ul>
                    </div>
                </div>
                <div class="rateb-trust-band">
                    <?php for ($oi = 1; $oi <= 6; $oi++) { ?>
                    <div class="rateb-trust-band__item"><span class="rateb-trust-band__k"><?php echo htmlspecialchars($ratebHome['home.ops.band.' . $oi . '.k'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-trust-band__v"><?php echo htmlspecialchars($ratebHome['home.ops.band.' . $oi . '.v'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-api-strip" id="api" data-rateb-marketing-depth="deep">
            <div class="rateb-container rateb-api-strip__inner">
                <div>
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.api.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-api-strip__title"><?php echo htmlspecialchars($ratebHome['home.api.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-api-strip__sub"><?php echo htmlspecialchars($ratebHome['home.api.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <a href="<?php echo htmlspecialchars(rateb_enterprise_mailto('RATEB — Contact Solutions Team'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--outline"><?php echo htmlspecialchars($ratebHome['home.api.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </section>
        <?php } ?>

        <section class="pricing-section rateb-pricing-saas" id="programs">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.pricing.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.pricing.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.pricing.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="pricing-row pricing-row--three">
            <div class="price-card price-card-starter">
                <span class="card-badge card-badge--muted"><?php echo htmlspecialchars($ratebHome['home.pricing.starter.badge'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-plan"><?php echo htmlspecialchars($ratebHome['home.pricing.starter.plan'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="card-subtitle"><?php echo htmlspecialchars($ratebHome['home.pricing.starter.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <p class="card-price-saas"><?php echo htmlspecialchars($ratebHome['home.pricing.starter.price_line'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <?php foreach ($ratebPricingStarterLines as $ratebLine) { ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($ratebLine, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php } ?>
                </ul>
                <a href="<?php echo htmlspecialchars($ratebRegisterHrefPro, ENT_QUOTES, 'UTF-8'); ?>" class="btn-register btn-register-starter"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratebHome['home.pricing.starter.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
            <div class="price-card gold price-card--featured">
                <span class="card-badge"><?php echo htmlspecialchars($ratebHome['home.pricing.gold.badge'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-plan"><?php echo htmlspecialchars($ratebHome['home.pricing.gold.plan_word'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="card-plan-note">list $<?php echo number_format((float)$goldListPriceYear1, 0); ?></span></div>
                <div class="card-subtitle"><?php echo htmlspecialchars($ratebHome['home.pricing.gold.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="plan-year-wrap">
                    <div class="plan-year-buttons">
                        <button type="button" class="year-btn gold-year-btn year-btn-card year-btn-neutral" data-years="0" data-price="<?php echo (float)$goldTestPriceMonth; ?>">Monthly<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$goldListPriceMonth, 2); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceMonth, 2); ?></span></span></button>
                        <button type="button" class="year-btn gold-year-btn year-btn-card year-btn-gold-active active" data-years="1" data-price="<?php echo (float)$goldTestPriceYear1; ?>">1 Year<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
                <p class="card-price-old" id="goldOldPrice">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></p>
                <p class="card-price" id="goldPrice">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?> <span id="goldPriceLabel">for 1 year</span></p>
                <span class="card-discount"><?php echo htmlspecialchars($ratebHome['home.pricing.gold.discount_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <?php foreach ($ratebPricingGoldLines as $ratebLine) { ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($ratebLine, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php } ?>
                </ul>
                <a href="<?php echo htmlspecialchars($ratebRegisterHrefGold, ENT_QUOTES, 'UTF-8'); ?>" id="goldRegisterBtn" class="btn-register"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratebHome['home.pricing.gold.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
            <div class="price-card platinum">
                <span class="card-badge"><?php echo htmlspecialchars($ratebHome['home.pricing.platinum.badge'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-plan"><?php echo htmlspecialchars($ratebHome['home.pricing.platinum.plan_word'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="card-plan-note">list $<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span></div>
                <div class="card-subtitle"><?php echo htmlspecialchars($ratebHome['home.pricing.platinum.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="plan-year-wrap">
                    <div class="plan-year-buttons">
                        <button type="button" class="year-btn platinum-year-btn year-btn-card year-btn-neutral" data-years="0" data-price="<?php echo (float)$platinumTestPriceMonth; ?>">Monthly<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$platinumListPriceMonth, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceMonth, 0); ?></span></span></button>
                        <button type="button" class="year-btn platinum-year-btn year-btn-card year-btn-platinum-active active" data-years="1" data-price="<?php echo (float)$platinumTestPriceYear1; ?>">1 Year<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
                <p class="card-price-old" id="platinumOldPrice">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></p>
                <p class="card-price" id="platinumPrice">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?> <span id="platinumPriceLabel">for 1 year</span></p>
                <span class="card-discount"><?php echo htmlspecialchars($ratebHome['home.pricing.platinum.discount_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <?php foreach ($ratebPricingPlatinumLines as $ratebLine) { ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($ratebLine, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php } ?>
                </ul>
                <a href="<?php echo htmlspecialchars($ratebRegisterHrefPlatinum, ENT_QUOTES, 'UTF-8'); ?>" id="platinumRegisterBtn" class="btn-register"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratebHome['home.pricing.platinum.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </div>
            </div>
        </section>

        <section class="register-section<?php echo $openRegister ? '' : ' register-section-hidden'; ?> rateb-register-wrap" id="register">
        <div class="rateb-info">
            <h2><i class="fas fa-info-circle me-2 register-info-icon"></i><?php echo htmlspecialchars($ratebHome['home.register.info.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><?php echo htmlspecialchars($ratebHome['home.register.info.intro'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <ul class="checklist">
                <?php for ($ci = 1; $ci <= 7; $ci++) { ?>
                <li><i class="fas fa-check-circle"></i><span><?php echo strip_tags($ratebHome['home.register.check.' . $ci] ?? '', '<strong>'); ?></span></li>
                <?php } ?>
            </ul>
        </div>
        <div class="form-card">
            <h1><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($ratebHome['home.register.form.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="subtitle">Request <?php echo htmlspecialchars($planLabel); ?> plan access<?php if ($planAmount): ?> — $<?php echo number_format($planAmount); ?><?php if ($years !== null): ?><?php if ((int)$years === 0): ?> per month<?php elseif ((int)$years > 0): ?> for <?php echo (int)$years; ?> year<?php echo (int)$years > 1 ? 's' : ''; ?><?php else: ?> setup<?php endif; ?><?php else: ?> setup<?php endif; ?><?php endif; ?>. We will review and contact you.</p>
            <div class="mb-3">
                <label class="form-label">Choose Plan</label>
                <p class="small mb-2 form-plan-hint"><i class="fas fa-info-circle me-1"></i><?php echo strip_tags($ratebHome['home.register.form.plan_hint'] ?? '', '<strong>'); ?></p>
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
                    <select class="form-control<?php echo $ratebCountryIsLocked ? ' is-locked-country' : ''; ?>" name="<?php echo $ratebCountryIsLocked ? 'country_visible' : 'country'; ?>" id="countrySelect" required <?php echo $ratebCountryIsLocked ? 'disabled' : ''; ?>>
                        <option value="">-- Select Country --</option>
                        <?php foreach ($countries as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($ratebCountryIsLocked && $ratebLockedCountryName === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($ratebCountryIsLocked): ?>
                    <input type="hidden" name="country" value="<?php echo htmlspecialchars($ratebLockedCountryName, ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="small mt-2 mb-0 form-plan-hint"><i class="fas fa-lock me-1"></i>Country is set by your portal.</p>
                    <?php endif; ?>
                </div>
                <div class="mb-3 is-hidden" id="otherCountryWrap"><label class="form-label">Specify country</label><input type="text" class="form-control" name="country_other" id="countryOther" maxlength="255" placeholder="Enter country name"></div>
                <div class="mb-3"><label class="form-label">Contact Email *</label><input type="email" class="form-control" name="contact_email" required maxlength="255" placeholder="you@example.com"></div>
                <div class="mb-3"><label class="form-label">Contact Phone *</label><input type="text" class="form-control" name="contact_phone" required maxlength="64" placeholder="+1234567890"></div>
                <div class="mb-3"><label class="form-label">Desired Site URL (optional)</label><input type="url" class="form-control" name="desired_site_url" maxlength="512" placeholder="https://your-agency.rateb.sa"></div>
                <div class="mb-4"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" maxlength="2000" placeholder="Tell us about your agency or requirements..."></textarea></div>
                
                <!-- When Pro selected: hint to choose Gold/Platinum for pricing summary -->
                <div id="paymentBlockPlaceholder" class="mb-4 <?php echo ($plan !== 'pro' && $planAmount) ? 'is-hidden' : ''; ?>">
                    <div class="payment-placeholder-box">
                        <i class="fas fa-receipt me-2 payment-placeholder-icon"></i><?php echo strip_tags($ratebHome['home.register.payment_placeholder'] ?? '', '<strong>'); ?>
                    </div>
                </div>
                <!-- Payment block: always in DOM; shown only for Gold/Platinum (JS toggles visibility) -->
                <div id="paymentBlockWrap" class="payment-block-wrap mb-4 <?php echo ($plan !== 'pro' && $planAmount) ? '' : 'is-hidden'; ?>">
                    <!-- Payment Summary -->
                    <div class="mb-4 payment-summary-box payment-summary-panel">
                        <h4 class="payment-summary-title"><i class="fas fa-receipt me-2"></i><?php echo htmlspecialchars($ratebHome['home.register.payment_summary.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
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
                            <span id="paymentSummaryTotal"><?php echo htmlspecialchars($ratebDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?> <?php echo $planAmount ? number_format(((float)$planAmount * 1.15 * (float)$ratebDisplayUsdRate), 2) : '0.00'; ?></span>
                        </div>
                        <?php
                        $__showNgeniusNote = ($plan !== 'pro' && $planAmount);
                        if ($__showNgeniusNote) {
                            $__usdTotal = (float) $planAmount * 1.15;
                            $__gatewayCurrency = strtoupper(trim((string) $ratebCheckoutCurrency));
                            if ($__gatewayCurrency === '') {
                                $__gatewayCurrency = 'SAR';
                            }
                            $__gatewayRate = ($__gatewayCurrency === 'SAR') ? (float) $ratebUsdToSar : 1.0;
                            if (!is_finite($__gatewayRate) || $__gatewayRate <= 0) {
                                $__gatewayRate = ($__gatewayCurrency === 'SAR') ? 3.75 : 1.0;
                            }
                            $__gatewayTotal = round($__usdTotal * $__gatewayRate, 2);
                            $__displayTotal = round($__usdTotal * $ratebDisplayUsdRate, 2);
                            ?>
                        <p class="small mb-0 mt-2 rateb-ngenius-currency-note">Card checkout is charged in <strong><?php echo htmlspecialchars($ratebDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?></strong>: <strong class="rateb-ngenius-sar-total"><?php echo htmlspecialchars($ratebDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format($__displayTotal, 2); ?></strong> <span class="rateb-ngenius-rate-note">(USD × <?php echo htmlspecialchars(number_format($ratebDisplayUsdRate, 2), ENT_QUOTES, 'UTF-8'); ?>)</span>.</p>
                        <?php if ($ratebDisplayCheckoutCurrency !== $__gatewayCurrency): ?>
                        <p class="small text-muted mb-0 mt-1 rateb-ngenius-currency-note">You will complete payment in <?php echo htmlspecialchars($__gatewayCurrency, ENT_QUOTES, 'UTF-8'); ?>.</p>
                        <?php endif; ?>
                        <?php } ?>
                    </div>
                    <p class="small mb-0 payment-summary-footnote"><i class="fas fa-file-invoice me-2 payment-summary-footnote-icon"></i><?php echo htmlspecialchars($ratebHome['home.register.payment_summary.footer'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                
                <button type="submit" class="btn btn-primary btn-submit" id="btnSubmit"><i class="fas fa-paper-plane me-2"></i><?php echo htmlspecialchars($ratebHome['home.register.submit'] ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
    </section>

        <section class="rateb-final-cta rateb-final-cta--enterprise" id="contact" aria-labelledby="rateb-final-cta-title">
            <div class="rateb-final-cta__bg" aria-hidden="true"></div>
            <div class="rateb-container rateb-final-cta__inner">
                <h2 id="rateb-final-cta-title" class="rateb-final-cta__title"><?php echo htmlspecialchars($ratebHome['home.final_cta.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="rateb-final-cta__sub"><?php echo htmlspecialchars($ratebHome['home.final_cta.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="rateb-final-cta__actions">
                    <a href="<?php echo htmlspecialchars(rateb_enterprise_mailto('RATEB — Request Enterprise Demo'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--primary rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.final_cta.btn_primary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="<?php echo htmlspecialchars($ratebWalkthroughHref, ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--outline rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.final_cta.btn_secondary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="<?php echo htmlspecialchars(rateb_enterprise_mailto('RATEB — Contact Solutions Team'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--outline rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.final_cta.btn_tertiary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="<?php echo htmlspecialchars(rateb_enterprise_mailto('RATEB — Request Security Brief'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--ghost rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.final_cta.btn_quaternary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </div>
        </section>
    

    <?php require __DIR__ . '/../includes/rateb-gallery-lightbox-markup.php'; ?>

</main>

<?php include __DIR__ . '/../includes/rateb-home-public-footer.php'; ?>

    <?php
    // EN: Pass server-side runtime values to JavaScript as JSON bootstrap.
    // AR: تمرير القيم المحسوبة من السيرفر إلى JavaScript بصيغة JSON.
    $ratebHomeBootstrap = [
        'checkoutCurrency' => $ratebCheckoutCurrency,
        'displayCheckoutCurrency' => $ratebDisplayCheckoutCurrency,
        'displayNgeniusLabel' => $ratebDisplayNgeniusLabel,
        'displayUsdRate' => (float) $ratebDisplayUsdRate,
        'usdToSar' => (float) $ratebUsdToSar,
        'openRegister' => $openRegister,
        'initialPlan' => $plan,
        'initialAmount' => $planAmount !== null ? (float) $planAmount : null,
        'initialYears' => $years !== null ? (int) $years : 1,
        'goldMonth' => (float) $goldTestPriceMonth,
        'goldYear1' => (float) $goldTestPriceYear1,
        'platinumMonth' => (float) $platinumTestPriceMonth,
        'platinumYear1' => (float) ($plans['platinum']['amount'] ?? $platinumTestPriceYear1),
    ];
    $ratebHomeJsPath = __DIR__ . '/../js/pages/home-page.js';
    clearstatcache(true, $ratebHomeJsPath);
    $ratebHomeJsTs = (int) (@filemtime($ratebHomeJsPath) ?: time());
    $ratebHomeJsQ = $ratebHomeJsTs . '-' . $ratebHomeUiRev . '-' . $ratebHomePhpMtime . $ratebHomeAssetExtraQ . '-c' . $ratebChromeBundleHash;

    $ratebGalleryLbJsPathHome = __DIR__ . '/../js/pages/rateb-gallery-lightbox.js';
    clearstatcache(true, $ratebGalleryLbJsPathHome);
    $ratebGalleryLbJsQHome = (int) (@filemtime($ratebGalleryLbJsPathHome) ?: time()) . '-' . $ratebHomeUiRev;
    ?>
    <script>
    (function () {
        var currentRev = <?php echo json_encode((string) $ratebCmsRev); ?>;
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
                var latest = localStorage.getItem('rateb_cms_rev') || '';
                if (latest && latest !== currentRev) {
                    go(latest);
                }
            } catch (e) {}
        }
        window.addEventListener('storage', function (ev) {
            if (ev && ev.key === 'rateb_cms_rev' && ev.newValue) {
                go(ev.newValue);
            }
        });
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) check();
        });
        window.addEventListener('focus', check);
    })();
    </script>
    <script type="application/json" id="rateb-home-bootstrap"><?php echo json_encode($ratebHomeBootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <?php
    ?>
    <?php
    $ratebProfileGuardJsHome = __DIR__ . '/../js/pages/rateb-profile-nav-guard.js';
    clearstatcache(true, $ratebProfileGuardJsHome);
    $ratebProfileGuardQHome = (string) (int) (@filemtime($ratebProfileGuardJsHome) ?: time());
    ?>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-profile-nav-guard.js?v=<?php echo htmlspecialchars($ratebProfileGuardQHome, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-gallery-lightbox.js?v=<?php echo htmlspecialchars($ratebGalleryLbJsQHome, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/home-page.js?v=<?php echo htmlspecialchars($ratebHomeJsQ, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-home-nav-chrome.js?v=<?php echo htmlspecialchars($ratebMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-marketing-focused.js?v=<?php echo htmlspecialchars($ratebMarketingFocusedJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>

    <!-- Chat Widget - Auto-answer support -->
    <button class="chat-widget-button" id="chatWidgetButton" aria-label="Open Chat"><i class="fas fa-comments"></i></button>
    <div class="chat-widget-container" id="chatWidgetContainer">
        <div class="chat-widget-header" data-chat-header-lock="1">
            <div class="chat-widget-header-info">
                <div class="chat-widget-header-avatar" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i></div>
                <div class="chat-widget-header-text"><h3><?php echo htmlspecialchars($ratebHome['home.chat.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><p class="online"><?php echo htmlspecialchars($ratebHome['home.chat.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></div>
            </div>
            <div class="chat-widget-header-actions">
                <button type="button" class="chat-widget-clear" id="chatWidgetClear" aria-label="Clear assistant conversation"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                <button type="button" class="chat-widget-close" id="chatWidgetClose" aria-label="Dismiss assistant chat"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="chat-widget-messages" id="chatWidgetMessages"></div>
        <div class="chat-widget-input-area">
            <div class="chat-widget-input-wrapper">
                <textarea class="chat-widget-input" id="chatWidgetInput" placeholder="Ask about register, domains, pricing… or: I need to talk to support" rows="1" data-chat-widget-placeholder="Ask about register, domains, pricing… or: I need to talk to support"></textarea>
                <button class="chat-widget-send" id="chatWidgetSend" aria-label="Send Message"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
    <script>window.RATEB_BASE_URL = <?php echo json_encode($baseUrl); ?>;</script>
    <?php $ratebPaymentJsVer = (int) (@filemtime(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'payment.js') ?: time()); ?>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/payment.js?v=<?php echo $ratebPaymentJsVer; ?>"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/help-center/help-center-builtin-content.js"></script>
    <?php
    $ratebChatWidgetJsPath = dirname(__DIR__) . '/js/chat-widget.js';
    clearstatcache(true, $ratebChatWidgetJsPath);
    $ratebChatWidgetJsQ = (string) (int) (@filemtime($ratebChatWidgetJsPath) ?: time());
    ?>
    <script>window.RATEB_CHAT_CONTEXT = 'public';</script>
    <?php
    require_once __DIR__ . '/../includes/rateb-public-chat-kb.php';
    $ratebPublicChatKbJson = json_encode(
        rateb_public_chat_kb_entries($baseUrl, is_array($ratebHome ?? null) ? $ratebHome : []),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    ?>
    <script>window.RATEB_PUBLIC_CHAT_KB = <?php echo $ratebPublicChatKbJson; ?>;</script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/chat-widget.js?v=<?php echo htmlspecialchars($ratebChatWidgetJsQ, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>


