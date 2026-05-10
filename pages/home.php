<?php
/**
 * Public: Home / landing page — English, layout like ratib.sa reference.
 * EN: Prepares server-side values (plans/currency/assets), renders page sections, and bootstraps JS config.
 * AR: يجهّز قيم السيرفر (الخطط/العملة/الأصول)، ويعرض أقسام الصفحة، ثم يمرر إعدادات JavaScript.
 */
require_once __DIR__ . '/../includes/config.php';

// Prevent stale HTML caching (browser + reverse proxies + some CDNs).
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
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

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;

// EN: Resolve hero video source:
// 1) CMS key home.video.file (URL or relative path), then
// 2) legacy assets/video.mp4 scan fallback.
$assetsDir = __DIR__ . '/../assets';
$videoExists = false;
$videoSrcRel = '';
$videoUrl = '';
$videoStored = '';

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
$ratibHome = ratib_site_content_home_flat();
$ratibDbFingerprint = function_exists('ratib_site_content_db_fingerprint')
    ? ratib_site_content_db_fingerprint()
    : '';
// Top bar: one DB round-trip for all keys so phone/WA/nodes stay in sync (no mixed JSON vs row timing).
$ratibTopbarKeys = [
    'home.topbar.phone_display',
    'home.topbar.wa_label',
    'home.topbar.tls_label',
    'home.topbar.nodes_count',
    'home.topbar.nodes_suffix',
    'home.topbar.client_login',
];
if (function_exists('ratib_site_content_fetch_key_values') && function_exists('ratib_site_content_defaults_home')) {
    $ratibDef = ratib_site_content_defaults_home();
    $liveTopbar = ratib_site_content_fetch_key_values($ratibTopbarKeys);
    foreach ($ratibTopbarKeys as $ratibTbKey) {
        if (!array_key_exists($ratibTbKey, $ratibDef)) {
            continue;
        }
        $ratibFallback = $ratibHome[$ratibTbKey] ?? $ratibDef[$ratibTbKey];
        $ratibHome[$ratibTbKey] = array_key_exists($ratibTbKey, $liveTopbar)
            ? $liveTopbar[$ratibTbKey]
            : $ratibFallback;
    }
} elseif (function_exists('ratib_site_content_get') && function_exists('ratib_site_content_defaults_home')) {
    $ratibDef = ratib_site_content_defaults_home();
    foreach ($ratibTopbarKeys as $ratibTbKey) {
        if (!array_key_exists($ratibTbKey, $ratibDef)) {
            continue;
        }
        $ratibFallback = $ratibHome[$ratibTbKey] ?? $ratibDef[$ratibTbKey];
        $ratibHome[$ratibTbKey] = ratib_site_content_get($ratibTbKey, $ratibFallback);
    }
}
// Visible phone text = exactly what the CMS saved (WYSIWYG). tel:/wa.me use normalized digits below.
$ratibPhoneRaw = (string) ($ratibHome['home.topbar.phone_display'] ?? '');
$ratibPhoneDigits = function_exists('ratib_site_content_phone_digits_for_links')
    ? ratib_site_content_phone_digits_for_links($ratibPhoneRaw)
    : (preg_replace('/\D+/', '', $ratibPhoneRaw) ?: '966599863868');
$ratibTopbarNodesDigits = preg_replace('/\D/', '', (string) ($ratibHome['home.topbar.nodes_count'] ?? '247'));
$ratibTopbarNodesNum = $ratibTopbarNodesDigits !== '' ? (int) $ratibTopbarNodesDigits : 247;
// Avoid billion-scale “counts” (usually a pasted phone fragment) confusing the top bar next to the phone.
if ($ratibTopbarNodesNum > 999999 || strlen($ratibTopbarNodesDigits) > 6) {
    $ratibTopbarNodesNum = 247;
    $ratibTopbarNodesDigits = '247';
}
$ratibPricingStarterLines = ratib_site_content_home_nl_lines($ratibHome['home.pricing.starter.features'] ?? '');
$ratibPricingGoldLines = ratib_site_content_home_nl_lines($ratibHome['home.pricing.gold.features'] ?? '');
$ratibPricingPlatinumLines = ratib_site_content_home_nl_lines($ratibHome['home.pricing.platinum.features'] ?? '');
$ratibProgFallbackRel = [
    1 => 'assets/images/program-preview-pipeline.svg',
    2 => 'assets/images/program-preview-workers.svg',
    3 => 'assets/images/program-preview-finance.svg',
];
$ratibProgFallbackFs = [
    1 => __DIR__ . '/../assets/images/program-preview-pipeline.svg',
    2 => __DIR__ . '/../assets/images/program-preview-workers.svg',
    3 => __DIR__ . '/../assets/images/program-preview-finance.svg',
];
$ratibProgSrc = [];
for ($rpi = 1; $rpi <= 10; $rpi++) {
    $stored = (string) ($ratibHome['home.program.img' . $rpi] ?? '');
    if ($rpi <= 3) {
        $ratibProgSrc[$rpi] = ratib_site_content_asset_url(
            $baseUrl,
            $stored,
            $ratibProgFallbackRel[$rpi],
            $ratibProgFallbackFs[$rpi]
        );
    } else {
        $ratibProgSrc[$rpi] = trim($stored) !== '' ? ratib_site_content_asset_url($baseUrl, $stored, '', __FILE__) : '';
    }
}
$ratibVideoSources = [];
$videoStored = trim((string) ($ratibHome['home.video.file'] ?? ''));
if ($videoStored !== '') {
    if (preg_match('#^https?://#i', $videoStored)) {
        $videoSrcRel = $videoStored;
        $videoUrl = $videoStored;
        $videoExists = true;
    } elseif (function_exists('ratib_site_content_media_public_url') && ratib_site_content_media_public_url($baseUrl, $videoStored) !== '') {
        $videoSrcRel = ratib_site_content_media_public_url($baseUrl, $videoStored);
        $videoUrl = $videoSrcRel;
        $videoExists = true;
    } else {
        $rel = ltrim(str_replace('\\', '/', $videoStored), '/');
        $fs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $v = is_file($fs) ? (int) filemtime($fs) : time();
        $videoUrl = rtrim($baseUrl, '/') . '/' . $rel . '?v=' . $v;
        $videoSrcRel = $videoUrl;
        $videoExists = true;
    }
}
if ($videoExists && $videoSrcRel !== '') {
    $ratibVideoSources[] = $videoSrcRel;
}
for ($vix = 2; $vix <= 6; $vix++) {
    $extraStored = trim((string) ($ratibHome['home.video.file' . $vix] ?? ''));
    if ($extraStored === '') {
        continue;
    }
    $u = '';
    if (preg_match('#^https?://#i', $extraStored)) {
        $u = $extraStored;
    } elseif (function_exists('ratib_site_content_media_public_url') && ratib_site_content_media_public_url($baseUrl, $extraStored) !== '') {
        $u = ratib_site_content_media_public_url($baseUrl, $extraStored);
    } else {
        $rel = ltrim(str_replace('\\', '/', $extraStored), '/');
        $fs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $v = is_file($fs) ? (int) filemtime($fs) : time();
        $u = rtrim($baseUrl, '/') . '/' . $rel . '?v=' . $v;
    }
    if ($u !== '') {
        $ratibVideoSources[] = $u;
    }
}
if (!$videoExists && !empty($ratibVideoSources)) {
    $videoSrcRel = (string) $ratibVideoSources[0];
    $videoUrl = $videoSrcRel;
    $videoExists = true;
}
if (!$videoExists) {
    // AR: توافق رجعي — دعم الملفات القديمة في assets حتى بدون مفتاح CMS.
    $videoPreferred = ['video.mp4', 'Ratib program.mp4', 'Ratib Program.mp4'];
    $videoPath = '';
    $videoFileName = '';
    foreach ($videoPreferred as $name) {
        $p = $assetsDir . DIRECTORY_SEPARATOR . $name;
        if (is_file($p)) {
            $videoPath = $p;
            $videoFileName = $name;
            break;
        }
    }
    if ($videoFileName === '' && is_dir($assetsDir)) {
        foreach (scandir($assetsDir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $full = $assetsDir . DIRECTORY_SEPARATOR . $f;
            if (!is_file($full)) {
                continue;
            }
            if (strtolower((string) pathinfo($f, PATHINFO_EXTENSION)) === 'mp4') {
                $videoPath = $full;
                $videoFileName = $f;
                break;
            }
        }
    }
    if ($videoFileName !== '') {
        $videoExists = true;
        $videoSrcRel = '../assets/' . rawurlencode($videoFileName);
        $videoUrl = $baseUrl . '/assets/' . rawurlencode($videoFileName);
        $ratibVideoSources[] = $videoSrcRel;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <!-- ratib-cms-build: site-content=<?php echo (int) (@filemtime(__DIR__ . '/../includes/site-content.php') ?: 0); ?> home-data=<?php echo (int) (@filemtime(__DIR__ . '/../includes/site-content-home-data.php') ?: 0); ?> load=<?php echo (int) (@filemtime(__DIR__ . '/../config/env/load.php') ?: 0); ?> cms-src=<?php echo htmlspecialchars(function_exists('ratib_site_content_public_source_resolved') ? ratib_site_content_public_source_resolved() : '', ENT_QUOTES, 'UTF-8'); ?> phone-len=<?php echo (int) strlen((string) ($ratibHome['home.topbar.phone_display'] ?? '')); ?> dbfp=<?php echo htmlspecialchars($ratibDbFingerprint, ENT_QUOTES, 'UTF-8'); ?> -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title><?php echo htmlspecialchars($ratibHome['home.meta.page_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/chat-widget.css">
    <?php $ratibHomeCssV = (int) (@filemtime(__DIR__ . '/../css/pages/home-public.css') ?: time()); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-public.css?v=<?php echo $ratibHomeCssV; ?>">
</head>
<body class="ratib-saas-home" data-ratib-home-layout="video-hero-program-svgs">

    <div class="ratib-saas-bg" aria-hidden="true">
        <div class="ratib-saas-bg__gradient"></div>
        <div class="ratib-saas-bg__grid"></div>
        <div class="ratib-saas-bg__orb ratib-saas-bg__orb--a"></div>
        <div class="ratib-saas-bg__orb ratib-saas-bg__orb--b"></div>
    </div>

    <div class="ratib-topbar">
        <div class="ratib-topbar__inner ratib-container">
            <div class="ratib-topbar__left">
                <a href="tel:+<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" class="ratib-topbar__link" dir="ltr"><i class="fas fa-phone-alt" aria-hidden="true"></i> <span class="ratib-topbar__phone-text"><?php echo htmlspecialchars($ratibPhoneRaw, ENT_QUOTES, 'UTF-8'); ?></span></a>
                <a href="https://wa.me/<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="ratib-topbar__wa" title="WhatsApp">
                    <span class="ratib-live-dot" aria-hidden="true"></span>
                    <?php echo htmlspecialchars($ratibHome['home.topbar.wa_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
            <div class="ratib-topbar__right">
                <span class="ratib-topbar__ops" aria-hidden="true" dir="ltr"><span class="ratib-mono-tag"><?php echo htmlspecialchars($ratibHome['home.topbar.tls_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-topbar__ops-sep">·</span><span class="ratib-mono-tag"><span id="ratib-topbar-nodes-counter" class="ratib-live-counter" data-ratib-counter="<?php echo htmlspecialchars($ratibTopbarNodesDigits, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $ratibTopbarNodesNum, ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratibHome['home.topbar.nodes_suffix'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></span>
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>" class="ratib-topbar__link"><?php echo htmlspecialchars($ratibHome['home.topbar.client_login'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <span class="ratib-topbar__lang" role="group" aria-label="Language">
                    <span class="ratib-lang ratib-lang--active">EN</span>
                    <span class="ratib-lang-sep">·</span>
                    <a href="<?php echo htmlspecialchars($baseUrl . '/pages/home.php'); ?>" class="ratib-lang" title="Arabic experience inside partner portals">AR</a>
                </span>
            </div>
        </div>
    </div>

    <header class="ratib-nav-shell" id="ratib-main-header">
        <div class="ratib-container ratib-nav-shell__inner">
            <a href="<?php echo htmlspecialchars($baseUrl . '/pages/home.php'); ?>" class="ratib-nav__brand">
                <img src="<?php echo htmlspecialchars($baseUrl . '/assets/ratib-logo.svg?v=3'); ?>" alt="RATIB" width="120" height="36">
                <span class="ratib-nav__brand-text">RATIB</span>
            </a>
            <button type="button" class="ratib-nav__toggle" id="ratibNavToggle" aria-label="Open menu" aria-expanded="false" aria-controls="ratibNavMenu">
                <span></span><span></span><span></span>
            </button>
            <nav class="ratib-nav__menu" id="ratibNavMenu" aria-label="Primary">
                <a href="#platform" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.platform'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#how-it-works" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.how_it_works'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#features" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.features'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#solutions" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.solutions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#programs" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.programs'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#agencies" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.agencies'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#tracking" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.tracking'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#operational" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.operational'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#api" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.api'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#contact" class="ratib-nav__link"><?php echo htmlspecialchars($ratibHome['home.nav.contact'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </nav>
            <div class="ratib-nav__cta">
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/partner-portal-login.php'); ?>" class="ratib-btn ratib-btn--ghost"><?php echo htmlspecialchars($ratibHome['home.nav.cta_partner'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#register" class="ratib-btn ratib-btn--primary js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1"><?php echo htmlspecialchars($ratibHome['home.nav.cta_primary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </div>
    </header>

    <main class="ratib-main">
        <!-- RATIB public home layout: product tour video directly under hero grid; program preview SVGs below. Deploy fingerprint: search HTML for id="video" on hero band + data-ratib-home-layout on body. -->
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
                    <div class="ratib-hero__actions">
                        <a href="#register" class="ratib-btn ratib-btn--primary ratib-btn--lg js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1"><?php echo htmlspecialchars($ratibHome['home.hero.cta_primary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                        <a href="#video" class="ratib-btn ratib-btn--outline ratib-btn--lg"><i class="fas fa-play" aria-hidden="true"></i> <?php echo htmlspecialchars($ratibHome['home.hero.cta_secondary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    </div>
                </div>
                <div class="ratib-hero__visual" aria-hidden="true">
                    <div class="ratib-dash">
                        <div class="ratib-dash__chrome">
                            <div class="ratib-dash__chrome-main">
                                <span class="ratib-dash__dot"></span><span class="ratib-dash__dot"></span><span class="ratib-dash__dot"></span>
                                <span class="ratib-dash__title">RATIB Command</span>
                                <span class="ratib-dash__live" title="Streaming telemetry"><span class="ratib-live-dot"></span> Live</span>
                            </div>
                            <div class="ratib-dash__chrome-sub ratib-mono-ops">
                                <span class="ratib-env-tag">prod</span>
                                <span class="ratib-dash__sep">·</span>
                                <span class="ratib-dash__panel-id" title="Control plane">cp-me-01a</span>
                                <span class="ratib-dash__sep">·</span>
                                <span class="ratib-dash__sync"><span class="ratib-sync-label">Edge sync</span> <span class="ratib-live-sync-age">2m</span></span>
                                <span class="ratib-dash__sep">·</span>
                                <span title="UTC session clock">UTC <time class="ratib-live-clock" datetime=""></time></span>
                            </div>
                        </div>
                        <div class="ratib-dash__body">
                            <div class="ratib-dash__sidebar">
                                <div class="ratib-dash__nav-item ratib-dash__nav-item--active">Pipeline</div>
                                <div class="ratib-dash__nav-item">Workers</div>
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
                                            <span>Recruitment records</span>
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
            </div>
            <div class="ratib-hero__video-band video-section ratib-video ratib-video--hero" id="video">
                <div class="ratib-container">
                    <header class="ratib-hero__video-head ratib-section__head ratib-section__head--left">
                        <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.video.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <h2 class="ratib-section__title ratib-hero__video-title"><?php echo htmlspecialchars($ratibHome['home.video.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="video-caption"><?php echo htmlspecialchars($ratibHome['home.video.caption'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </header>
                    <div class="ratib-video__shell">
                        <div class="video-wrap">
                            <?php if ($videoExists): ?>
                            <video controls preload="metadata" class="home-video-player" playsinline>
                                <source src="<?php echo htmlspecialchars($videoSrcRel, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                Your browser does not support the video tag. <a href="<?php echo htmlspecialchars($videoSrcRel, ENT_QUOTES, 'UTF-8'); ?>">Download the video</a>.
                            </video>
                            <?php else: ?>
                            <div class="video-fallback-box">
                                <i class="fas fa-video-slash fa-3x mb-3"></i>
                                <p>Add an MP4 to <code>assets/</code> — recommended name: <code>video.mp4</code></p>
                                <p class="small mb-0">Any <strong>.mp4</strong> file in the <code>assets</code> folder will be picked up automatically.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ratib-hero__photo-strip ratib-hero__program-strip">
                <div class="ratib-container">
                    <p class="ratib-hero__photo-eyebrow"><?php echo htmlspecialchars($ratibHome['home.program.strip_eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="ratib-hero__photo-grid" role="list">
                        <?php for ($pgi = 1; $pgi <= 10; $pgi++) { ?>
                        <?php if (($ratibProgSrc[$pgi] ?? '') === '') { continue; } ?>
                        <figure class="ratib-hero__photo ratib-hero__photo--program" role="listitem">
                            <img src="<?php echo htmlspecialchars((string) $ratibProgSrc[$pgi], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($ratibHome['home.program.alt.' . $pgi] ?? '', ENT_QUOTES, 'UTF-8'); ?>" width="800" height="500" loading="lazy" decoding="async">
                            <figcaption><?php echo htmlspecialchars($ratibHome['home.program.caption.' . $pgi] ?? '', ENT_QUOTES, 'UTF-8'); ?></figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                    <?php if (count($ratibVideoSources) > 1): ?>
                    <div class="row g-3 mt-2">
                        <?php foreach (array_slice($ratibVideoSources, 1) as $extraVideoSrc): ?>
                        <div class="col-12 col-md-6">
                            <video controls preload="metadata" class="home-video-player w-100" playsinline>
                                <source src="<?php echo htmlspecialchars($extraVideoSrc, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                            </video>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

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

        <section class="ratib-section ratib-how" id="how-it-works">
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
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas <?php echo htmlspecialchars($fic, ENT_QUOTES, 'UTF-8'); ?>"></i></div><h3><?php echo htmlspecialchars($ratibHome['home.features.' . $fi . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><p><?php echo htmlspecialchars($ratibHome['home.features.' . $fi . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-pipeline-section" id="tracking">
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

        <section class="ratib-section ratib-ai-section" id="solutions">
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

        <section class="ratib-section ratib-eco" id="agencies">
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

        <section class="ratib-section ratib-analytics">
            <div class="ratib-container">
                <header class="ratib-section__head ratib-section__head--left">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.analytics.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.analytics.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.analytics.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="ratib-analytics__grid">
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops"><?php echo htmlspecialchars($ratibHome['home.analytics.1.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratibHome['home.analytics.1.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="ratib-metric"><span class="ratib-metric__val ratib-live-nudge" data-ratib-jitter-pct="<?php echo htmlspecialchars(preg_replace('/[^\d.]/', '', (string) ($ratibHome['home.analytics.1.metric'] ?? '98.2')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.analytics.1.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-metric__chart ratib-metric__chart--line" aria-hidden="true"></span></div><p><?php echo htmlspecialchars($ratibHome['home.analytics.1.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops"><?php echo htmlspecialchars($ratibHome['home.analytics.2.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratibHome['home.analytics.2.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="ratib-metric"><span class="ratib-metric__val"><?php echo htmlspecialchars($ratibHome['home.analytics.2.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-metric__chart ratib-metric__chart--bars" aria-hidden="true"></span></div><p><?php echo htmlspecialchars($ratibHome['home.analytics.2.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops"><?php echo htmlspecialchars($ratibHome['home.analytics.3.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratibHome['home.analytics.3.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="ratib-metric"><span class="ratib-metric__val"><?php echo htmlspecialchars($ratibHome['home.analytics.3.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-metric__note"><?php echo htmlspecialchars($ratibHome['home.analytics.3.note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div><p><?php echo htmlspecialchars($ratibHome['home.analytics.3.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops"><?php echo htmlspecialchars($ratibHome['home.analytics.4.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratibHome['home.analytics.4.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="ratib-metric"><span class="ratib-metric__val"><?php echo htmlspecialchars($ratibHome['home.analytics.4.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-metric__note"><?php echo htmlspecialchars($ratibHome['home.analytics.4.note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div><p><?php echo htmlspecialchars($ratibHome['home.analytics.4.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-ops-visibility" id="operational">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.ops.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-section__title"><?php echo htmlspecialchars($ratibHome['home.ops.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-section__sub"><?php echo htmlspecialchars($ratibHome['home.ops.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="ratib-ops__layout">
                    <div class="ratib-ops__panel ratib-ops__panel--preview">
                        <div class="ratib-ops__panel-bar">
                            <span class="ratib-mono-tag">visibility.prod.ratib · panel</span>
                            <span class="ratib-pill ratib-pill--live"><span class="ratib-live-dot" aria-hidden="true"></span> streaming</span>
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
                                <span class="ratib-ops__mini-sub">GPS &amp; checkpoint pings within variance</span>
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

        <section class="ratib-section ratib-api-strip" id="api">
            <div class="ratib-container ratib-api-strip__inner">
                <div>
                    <p class="ratib-eyebrow"><?php echo htmlspecialchars($ratibHome['home.api.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="ratib-api-strip__title"><?php echo htmlspecialchars($ratibHome['home.api.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="ratib-api-strip__sub"><?php echo htmlspecialchars($ratibHome['home.api.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <a href="#contact" class="ratib-btn ratib-btn--outline"><?php echo htmlspecialchars($ratibHome['home.api.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </section>

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
                <a href="#register" class="btn-register btn-register-starter js-open-register" data-register-plan="pro" data-register-amount="" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratibHome['home.pricing.starter.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
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
                <a href="#register" id="goldRegisterBtn" class="btn-register js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratibHome['home.pricing.gold.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
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
                <a href="#register" id="platinumRegisterBtn" class="btn-register js-open-register" data-register-plan="platinum" data-register-amount="<?php echo (float)($plans['platinum']['amount'] ?? $platinumTestPriceYear1); ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratibHome['home.pricing.platinum.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </div>
            </div>
        </section>

        <section class="register-section register-section-hidden ratib-register-wrap" id="register">
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

        <section class="ratib-final-cta" aria-labelledby="ratib-final-cta-title">
            <div class="ratib-final-cta__bg" aria-hidden="true"></div>
            <div class="ratib-container ratib-final-cta__inner">
                <h2 id="ratib-final-cta-title" class="ratib-final-cta__title"><?php echo htmlspecialchars($ratibHome['home.final_cta.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="ratib-final-cta__sub"><?php echo htmlspecialchars($ratibHome['home.final_cta.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="ratib-final-cta__actions">
                    <a href="#register" class="ratib-btn ratib-btn--primary ratib-btn--lg js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1"><?php echo htmlspecialchars($ratibHome['home.final_cta.btn_primary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="mailto:ratibsrar@gmail.com?subject=RATIB%20platform%20demo%20request" class="ratib-btn ratib-btn--outline ratib-btn--lg"><?php echo htmlspecialchars($ratibHome['home.final_cta.btn_secondary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </div>
        </section>
    </main>

    <footer class="ratib-footer-enterprise" id="contact">
        <div class="ratib-container ratib-footer-enterprise__grid">
            <div class="ratib-footer-enterprise__brand">
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/home.php'); ?>" class="ratib-footer-enterprise__logo">
                    <img src="<?php echo htmlspecialchars($baseUrl . '/assets/ratib-logo.svg?v=3'); ?>" alt="RATIB" width="112" height="32">
                </a>
                <p><?php echo htmlspecialchars($ratibHome['home.footer.brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.platform'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul>
                    <li><a href="#platform"><?php echo htmlspecialchars($ratibHome['home.footer.link.platform.overview'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="#how-it-works"><?php echo htmlspecialchars($ratibHome['home.nav.how_it_works'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="#features"><?php echo htmlspecialchars($ratibHome['home.nav.features'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="#tracking"><?php echo htmlspecialchars($ratibHome['home.nav.tracking'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="#operational"><?php echo htmlspecialchars($ratibHome['home.footer.link.platform.ops_visibility'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="#programs"><?php echo htmlspecialchars($ratibHome['home.nav.programs'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="#api"><?php echo htmlspecialchars($ratibHome['home.footer.link.platform.apis'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.company'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul>
                    <li><a href="#solutions"><?php echo htmlspecialchars($ratibHome['home.footer.link.solutions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="#agencies"><?php echo htmlspecialchars($ratibHome['home.nav.agencies'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="#operational"><?php echo htmlspecialchars($ratibHome['home.nav.operational'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="#video"><?php echo htmlspecialchars($ratibHome['home.footer.link.demo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>">Customer portal</a></li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.support'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/login.php'); ?>">Support tickets</a></li>
                    <li><a href="https://wa.me/<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a></li>
                    <li><a href="tel:+<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" dir="ltr"><span class="ratib-topbar__phone-text"><?php echo htmlspecialchars($ratibPhoneRaw, ENT_QUOTES, 'UTF-8'); ?></span></a></li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.legal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul>
                    <li><a href="#register"><?php echo htmlspecialchars($ratibHome['home.footer.link.service_registration'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="mailto:ratibsrar@gmail.com">ratibsrar@gmail.com</a></li>
                </ul>
            </div>
            <div class="ratib-footer-col ratib-footer-enterprise__infra">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.infra'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <p class="ratib-footer-enterprise__infra-copy"><?php echo htmlspecialchars($ratibHome['home.footer.infra.copy'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="ratib-footer-social">
                    <a href="https://wa.me/<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="mailto:ratibsrar@gmail.com" aria-label="Email"><i class="fas fa-envelope"></i></a>
                </div>
                <div class="footer-subscribe ratib-footer-newsletter">
                    <label class="ratib-footer-newsletter__label" for="footerEmail"><?php echo htmlspecialchars($ratibHome['home.footer.newsletter.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="email" placeholder="<?php echo htmlspecialchars($ratibHome['home.footer.newsletter.placeholder'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" id="footerEmail" name="footer_email" autocomplete="email" aria-label="Email for newsletter">
                    <button type="button" class="btn-sub" id="footerSubscribe"><?php echo htmlspecialchars($ratibHome['home.footer.newsletter.button'] ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </div>
        </div>
        <div class="ratib-footer-system-strip">
            <div class="ratib-container ratib-footer-system-strip__inner">
                <span class="ratib-footer-system-strip__item"><span class="ratib-mono-tag">uptime</span> <?php echo htmlspecialchars($ratibHome['home.footer.strip.1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="ratib-footer-system-strip__item"><span class="ratib-mono-tag">requests</span> <?php echo htmlspecialchars($ratibHome['home.footer.strip.2'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="ratib-footer-system-strip__item"><span class="ratib-mono-tag">events</span> <?php echo htmlspecialchars($ratibHome['home.footer.strip.3'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="ratib-footer-enterprise__bottom">
            <div class="ratib-container ratib-footer-enterprise__bottom-inner">
                <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($ratibHome['home.footer.copyright_suffix'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="ratib-footer-enterprise__loc"><i class="fas fa-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($ratibHome['home.footer.location'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </footer>

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
    $ratibHomeJsV = (int) (@filemtime(__DIR__ . '/../js/pages/home-page.js') ?: time());
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
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/home-page.js?v=<?php echo $ratibHomeJsV; ?>"></script>

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
</body>
</html>

