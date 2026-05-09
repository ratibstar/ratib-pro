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

// EN: Resolve hero video source from preferred names, then fallback to any MP4 in /assets.
// AR: تحديد فيديو العرض من أسماء مفضلة أولاً ثم الرجوع لأي ملف MP4 داخل /assets.
// Video: prefer assets/video.mp4; also accept common uploads (e.g. "Ratib program.mp4") or any single .mp4 in assets/
$assetsDir = __DIR__ . '/../assets';
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
$videoExists = $videoFileName !== '';
$videoSrcRel = $videoExists ? ('../assets/' . rawurlencode($videoFileName)) : '';
$videoUrl = $videoExists ? ($baseUrl . '/assets/' . rawurlencode($videoFileName)) : '';

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
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title>RATIB — Enterprise Recruitment OS &amp; Workforce Intelligence Platform</title>
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
                <a href="tel:+966599863868" class="ratib-topbar__link"><i class="fas fa-phone-alt" aria-hidden="true"></i> +966 59 986 3868</a>
                <a href="https://wa.me/966599863868" target="_blank" rel="noopener noreferrer" class="ratib-topbar__wa" title="WhatsApp">
                    <span class="ratib-live-dot" aria-hidden="true"></span>
                    Live on WhatsApp
                </a>
            </div>
            <div class="ratib-topbar__right">
                <span class="ratib-topbar__ops" aria-hidden="true"><span class="ratib-mono-tag">TLS 1.3</span><span class="ratib-topbar__ops-sep">·</span><span class="ratib-mono-tag"><span class="ratib-live-counter" data-ratib-counter="247">247</span> nodes</span></span>
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>" class="ratib-topbar__link">Client login</a>
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
                <a href="#platform" class="ratib-nav__link">Platform</a>
                <a href="#how-it-works" class="ratib-nav__link">How it works</a>
                <a href="#features" class="ratib-nav__link">Features</a>
                <a href="#solutions" class="ratib-nav__link">Solutions</a>
                <a href="#programs" class="ratib-nav__link">Pricing</a>
                <a href="#agencies" class="ratib-nav__link">Agencies</a>
                <a href="#tracking" class="ratib-nav__link">Tracking</a>
                <a href="#operational" class="ratib-nav__link">Visibility</a>
                <a href="#api" class="ratib-nav__link">API</a>
                <a href="#contact" class="ratib-nav__link">Contact</a>
            </nav>
            <div class="ratib-nav__cta">
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/partner-portal-login.php'); ?>" class="ratib-btn ratib-btn--ghost">Partner Login</a>
                <a href="#register" class="ratib-btn ratib-btn--primary js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1">Start agency infrastructure</a>
            </div>
        </div>
    </header>

    <main class="ratib-main">
        <!-- RATIB public home layout: product tour video directly under hero grid; program preview SVGs below. Deploy fingerprint: search HTML for id="video" on hero band + data-ratib-home-layout on body. -->
        <section class="ratib-hero">
            <div class="ratib-container ratib-hero__grid">
                <div class="ratib-hero__copy">
                    <p class="ratib-eyebrow">Recruitment Automation &amp; Tracking Intelligence Base</p>
                    <h1 class="ratib-hero__title">Recruitment Automation &amp; <span class="ratib-text-gradient">Workforce Intelligence</span></h1>
                    <p class="ratib-hero__lead">Production control plane for sending-country agencies and host-market programs: lifecycle orchestration, workforce telemetry, compliance gates, and ledger-linked billing—same surfaces operations teams use daily, not a marketing shell.</p>
                    <ul class="ratib-hero__bullets">
                        <li><i class="fas fa-diagram-project"></i> Workflow orchestration &amp; stage sync across sending &amp; host markets</li>
                        <li><i class="fas fa-building-user"></i> Tenant isolation, RBAC, and per-agency domain edges</li>
                        <li><i class="fas fa-location-crosshairs"></i> Field &amp; milestone telemetry with SLA visibility</li>
                        <li><i class="fas fa-bolt"></i> Event-driven signals, escalations, and operational intelligence</li>
                    </ul>
                    <div class="ratib-hero__actions">
                        <a href="#register" class="ratib-btn ratib-btn--primary ratib-btn--lg js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1">Launch operations workspace</a>
                        <a href="#video" class="ratib-btn ratib-btn--outline ratib-btn--lg"><i class="fas fa-play" aria-hidden="true"></i> Platform walkthrough</a>
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
                        <p class="ratib-eyebrow">Product tour</p>
                        <h2 class="ratib-section__title ratib-hero__video-title">Walk the surfaces your teams will run</h2>
                        <p class="video-caption">Recorded walkthrough: pipelines, verification queues, finance hooks, and agency administration.</p>
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
                    <p class="ratib-hero__photo-eyebrow">Program previews</p>
                    <div class="ratib-hero__photo-grid" role="list">
                        <figure class="ratib-hero__photo ratib-hero__photo--program" role="listitem">
                            <img src="<?php echo htmlspecialchars($baseUrl . '/assets/images/program-preview-pipeline.svg?v=' . (int) (@filemtime(__DIR__ . '/../assets/images/program-preview-pipeline.svg') ?: 1), ENT_QUOTES, 'UTF-8'); ?>" alt="RATIB pipeline board with stages, SLA, and worker rows" width="800" height="500" loading="lazy" decoding="async">
                            <figcaption>Pipeline board</figcaption>
                        </figure>
                        <figure class="ratib-hero__photo ratib-hero__photo--program" role="listitem">
                            <img src="<?php echo htmlspecialchars($baseUrl . '/assets/images/program-preview-workers.svg?v=' . (int) (@filemtime(__DIR__ . '/../assets/images/program-preview-workers.svg') ?: 1), ENT_QUOTES, 'UTF-8'); ?>" alt="RATIB workers registry with stages, owners, and GPS context" width="800" height="500" loading="lazy" decoding="async">
                            <figcaption>Workers registry</figcaption>
                        </figure>
                        <figure class="ratib-hero__photo ratib-hero__photo--program" role="listitem">
                            <img src="<?php echo htmlspecialchars($baseUrl . '/assets/images/program-preview-finance.svg?v=' . (int) (@filemtime(__DIR__ . '/../assets/images/program-preview-finance.svg') ?: 1), ENT_QUOTES, 'UTF-8'); ?>" alt="RATIB finance view with invoices, throughput, and connector latency" width="800" height="500" loading="lazy" decoding="async">
                            <figcaption>Finance &amp; ledger</figcaption>
                        </figure>
                    </div>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-trust" id="platform">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <h2 class="ratib-section__title">Built for regulated, high-volume recruitment operations</h2>
                    <p class="ratib-section__sub">Deployed as a shared control plane: tenant-isolated data paths, encrypted transit, immutable workflow history, and finance-grade events organizations can reconcile—not narrative dashboards.</p>
                </header>
                <div class="ratib-trust__grid">
                    <article class="ratib-trust-card"><div class="ratib-trust-card__icon"><i class="fas fa-user-shield"></i></div><h3>RBAC &amp; scoped tenancy</h3><p>Role matrices per agency branch; least-privilege API keys; segregated operator sessions.</p></article>
                    <article class="ratib-trust-card"><div class="ratib-trust-card__icon"><i class="fas fa-clock-rotate-left"></i></div><h3>Audit trails &amp; workflow history</h3><p>Append-only stage transitions with actor, correlation id, and policy version stamped on each commit.</p></article>
                    <article class="ratib-trust-card"><div class="ratib-trust-card__icon"><i class="fas fa-lock"></i></div><h3>Encrypted infrastructure</h3><p>TLS 1.3 to the edge; tenant-scoped storage; session revocation and device-aware policies.</p></article>
                    <article class="ratib-trust-card"><div class="ratib-trust-card__icon"><i class="fas fa-stopwatch"></i></div><h3>SLA visibility</h3><p>Stage clocks, breach watches, and escalation routes before commitments slip—surfaced in ops consoles.</p></article>
                    <article class="ratib-trust-card"><div class="ratib-trust-card__icon"><i class="fas fa-clipboard-check"></i></div><h3>Compliance tracking</h3><p>Embassy, medical, and police bundles tracked as first-class artifacts with reviewer attribution.</p></article>
                    <article class="ratib-trust-card"><div class="ratib-trust-card__icon"><i class="fas fa-server"></i></div><h3>Continuity &amp; multi-region readiness</h3><p>Operational backups, replayable event streams, and expansion paths for secondary regions when procurement requires it.</p></article>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-how" id="how-it-works">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow">Operational onboarding</p>
                    <h2 class="ratib-section__title">How agencies go live on RATIB</h2>
                    <p class="ratib-section__sub">From tenant provisioning to invoicing—one orchestrated spine with explicit human gates, auditable transitions, and connector-backed finance.</p>
                </header>
                <ol class="ratib-how__steps" aria-label="Deployment sequence">
                    <li class="ratib-how__step"><span class="ratib-how__n" aria-hidden="true">01</span><strong class="ratib-how__title">Agency onboarding</strong><span class="ratib-how__desc">Tenant creation, RBAC, branded domains, sandbox → production promotion.</span></li>
                    <li class="ratib-how__step"><span class="ratib-how__n" aria-hidden="true">02</span><strong class="ratib-how__title">Workflow configuration</strong><span class="ratib-how__desc">Stage graph, owners, SLA clocks, and verification bundles per corridor.</span></li>
                    <li class="ratib-how__step"><span class="ratib-how__n" aria-hidden="true">03</span><strong class="ratib-how__title">Candidate intake</strong><span class="ratib-how__desc">Structured records, document capture, and deduped applicant system of record.</span></li>
                    <li class="ratib-how__step"><span class="ratib-how__n" aria-hidden="true">04</span><strong class="ratib-how__title">Stage orchestration</strong><span class="ratib-how__desc">Automated hops plus HITL approvals; correlation ids across workers and finance.</span></li>
                    <li class="ratib-how__step"><span class="ratib-how__n" aria-hidden="true">05</span><strong class="ratib-how__title">Tracking &amp; compliance</strong><span class="ratib-how__desc">GPS and milestone telemetry with policy-bound exception routing.</span></li>
                    <li class="ratib-how__step"><span class="ratib-how__n" aria-hidden="true">06</span><strong class="ratib-how__title">Arrival &amp; deployment</strong><span class="ratib-how__desc">Host-market handover, closure events, and workforce activation signals.</span></li>
                    <li class="ratib-how__step"><span class="ratib-how__n" aria-hidden="true">07</span><strong class="ratib-how__title">Reporting &amp; invoicing</strong><span class="ratib-how__desc">Executive telemetry, branch roll-ups, and ledger-linked issuance.</span></li>
                </ol>
            </div>
        </section>

        <section class="ratib-section" id="features">
            <div class="ratib-container">
                <header class="ratib-section__head ratib-section__head--left">
                    <p class="ratib-eyebrow">Platform surface</p>
                    <h2 class="ratib-section__title">Twelve capabilities operators touch daily</h2>
                    <p class="ratib-section__sub ratib-section__sub--inline">Same modules used in production consoles—not vapor features.</p>
                </header>
                <div class="ratib-feature-grid">
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-gears"></i></div><h3>Recruitment lifecycle engine</h3><p>Define stages, owners, policies once—execute across every worker file.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-id-badge"></i></div><h3>Applicant system of record</h3><p>Single longitudinal record: docs, history, readiness for deployment.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-shuffle"></i></div><h3>Stage synchronization</h3><p>Event- and time-driven transitions with explicit human-in-the-loop gates.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-location-dot"></i></div><h3>Field &amp; GPS telemetry</h3><p>Check-ins, corridors, and exception routing for operational visibility.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-globe"></i></div><h3>Multi-domain tenancy</h3><p>Agency-branded edges on shared orchestration and identity substrate.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-file-signature"></i></div><h3>Digital contracts</h3><p>Signatures and renewals bound to lifecycle state transitions.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-coins"></i></div><h3>Operational finance hooks</h3><p>Placement-to-settlement awareness for controllers and agency billing.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-receipt"></i></div><h3>E-invoicing rails</h3><p>Issuance when rules and verified events align—auditable downstream.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-route"></i></div><h3>Worker lifecycle trace</h3><p>Immutable checkpoints from intake through arrival and handover.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-bell"></i></div><h3>Operational alerting</h3><p>Escalations to ops, agencies, and partners before SLA breach.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-chart-pie"></i></div><h3>Telemetry &amp; analytics</h3><p>Funnel integrity, velocity, and cohort quality in one executive surface.</p></article>
                    <article class="ratib-feature-card"><div class="ratib-feature-card__icon"><i class="fas fa-plug"></i></div><h3>Integration &amp; API fabric</h3><p>HRIS, ERP, messaging, and verification feeds via authenticated endpoints.</p></article>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-pipeline-section" id="tracking">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow">Orchestration graph</p>
                    <h2 class="ratib-section__title">End-to-end pipeline, instrumented</h2>
                    <p class="ratib-section__sub">Each hop emits events to the orchestrator: automation runs, manual gates, document verification, and field telemetry in one auditable spine.</p>
                </header>
                <div class="ratib-pipeline" role="list">
                    <div class="ratib-pipeline__track" aria-hidden="true"></div>
                    <div class="ratib-pipeline__item ratib-pipeline__item--complete" role="listitem"><span class="ratib-pipeline__dot"></span><span class="ratib-pipeline__label">Application</span><span class="ratib-pipeline__meta">committed · 09 May 08:14 UTC</span></div>
                    <div class="ratib-pipeline__item ratib-pipeline__item--complete" role="listitem"><span class="ratib-pipeline__dot"></span><span class="ratib-pipeline__label">Verification</span><span class="ratib-pipeline__meta">bundle OK · reviewer svc-bot</span></div>
                    <div class="ratib-pipeline__item ratib-pipeline__item--active" role="listitem"><span class="ratib-pipeline__dot"></span><span class="ratib-pipeline__label">Medical</span><span class="ratib-pipeline__meta">clearance window · SLA 38h</span></div>
                    <div class="ratib-pipeline__item" role="listitem"><span class="ratib-pipeline__dot"></span><span class="ratib-pipeline__label">Embassy</span><span class="ratib-pipeline__meta">slot queue · RUH consulate</span></div>
                    <div class="ratib-pipeline__item" role="listitem"><span class="ratib-pipeline__dot"></span><span class="ratib-pipeline__label">Visa</span><span class="ratib-pipeline__meta">issue pending · workflow hold</span></div>
                    <div class="ratib-pipeline__item" role="listitem"><span class="ratib-pipeline__dot"></span><span class="ratib-pipeline__label">Ticket</span><span class="ratib-pipeline__meta">carrier manifest · auto</span></div>
                    <div class="ratib-pipeline__item" role="listitem"><span class="ratib-pipeline__dot"></span><span class="ratib-pipeline__label">Arrival</span><span class="ratib-pipeline__meta">handover GPS · geofence</span></div>
                    <div class="ratib-pipeline__item" role="listitem"><span class="ratib-pipeline__dot"></span><span class="ratib-pipeline__label">Deployment</span><span class="ratib-pipeline__meta">FIN close · INV emitted</span></div>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-ai-section" id="solutions">
            <div class="ratib-container">
                <header class="ratib-section__head ratib-section__head--left">
                    <p class="ratib-eyebrow">Operational scenarios</p>
                    <h2 class="ratib-section__title">Where RATIB runs in production</h2>
                    <p class="ratib-section__sub">Representative B2B programs on the same orchestration core—multi-tenant, audit-visible, connector-backed.</p>
                </header>
                <div class="ratib-ai-grid ratib-use-grid">
                    <article class="ratib-ai-card ratib-ai-card--wide ratib-use-card ratib-use-card--wide">
                        <h3>Recruitment agencies · multi-branch</h3>
                        <p>Central intake with branch-level RBAC, quota splits, and consolidated reporting for owners—without duplicating worker records across offices.</p>
                        <div class="ratib-ai-visual ratib-use-visual">
                            <div class="ratib-ai-row"><span class="ratib-pill">Tenant</span> ACME · branches RUH · JED · DMM · shared pipeline graph</div>
                            <div class="ratib-ai-row"><span class="ratib-pill ratib-pill--accent">Ops</span> stage owners mapped · SLA inherited from policy CL-2024-ME</div>
                            <div class="ratib-ai-row"><span class="ratib-pill">Emit</span> nightly cohort rollup · exec dashboard · no CSV extracts</div>
                        </div>
                    </article>
                    <article class="ratib-ai-card ratib-use-card">
                        <h3>Overseas workforce operations</h3>
                        <p>Corridor programs with sending-country compliance packs, host-market deployment rules, and milestone telemetry tied to billing milestones.</p>
                    </article>
                    <article class="ratib-ai-card ratib-use-card">
                        <h3>Multi-office recruitment firms</h3>
                        <p>Shared candidate inventory with segregated finance and placement attribution—one platform, strict tenant edges between brands.</p>
                    </article>
                    <article class="ratib-ai-card ratib-use-card">
                        <h3>Enterprise staffing coordination</h3>
                        <p>Buyer mandates, bulk transitions, and SLA-backed escalations when intake spikes or sponsor deadlines move.</p>
                    </article>
                    <article class="ratib-ai-card ratib-use-card">
                        <h3>Embassy processing workflows</h3>
                        <p>Appointment queues, bundle completeness checks, and status feeds operators defend in audits—linked to worker files, not inboxes.</p>
                    </article>
                    <article class="ratib-ai-card ratib-use-card">
                        <h3>Visa pipeline management</h3>
                        <p>Medical → embassy → visa → ticket orchestration with explicit holds, reviewer attribution, and finance triggers only after verified hops.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-eco" id="agencies">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow">Multi-agency ecosystem</p>
                    <h2 class="ratib-section__title">One RATIB core. Many independent agencies.</h2>
                    <p class="ratib-section__sub">Isolated production tenants on a shared control plane—identity, orchestration, telemetry, and finance connectors without duplicating stacks per agency.</p>
                </header>
                <div class="ratib-eco__viz" aria-hidden="true">
                    <div class="ratib-eco__core">
                        <span class="ratib-eco__core-label">RATIB Core</span>
                        <span class="ratib-eco__core-sub">IAM · Orchestrator · Telemetry · Ledger API</span>
                    </div>
                    <div class="ratib-eco__spokes">
                        <div class="ratib-eco__spoke"><span>Agency A</span><small>tenant + domain</small></div>
                        <div class="ratib-eco__spoke"><span>Agency B</span><small>tenant + domain</small></div>
                        <div class="ratib-eco__spoke"><span>Agency C</span><small>tenant + domain</small></div>
                        <div class="ratib-eco__spoke ratib-eco__spoke--accent"><span>Custom domains</span><small>white-label edges</small></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-analytics">
            <div class="ratib-container">
                <header class="ratib-section__head ratib-section__head--left">
                    <p class="ratib-eyebrow">Telemetry plane</p>
                    <h2 class="ratib-section__title">Executive &amp; ops signals from live programs</h2>
                    <p class="ratib-section__sub">Rolling aggregates from committed lifecycle events—same metrics surfaced in operational reviews.</p>
                </header>
                <div class="ratib-analytics__grid">
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops">snapshot · merged shards · UTC</p><h3>Checkpoint fidelity</h3><div class="ratib-metric"><span class="ratib-metric__val ratib-live-nudge" data-ratib-jitter-pct="98.2">98.2%</span><span class="ratib-metric__chart ratib-metric__chart--line" aria-hidden="true"></span></div><p>Completed checkpoints vs policy graph for in-motion cohorts.</p></article>
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops">queue depth · 15m resolution</p><h3>Active lifecycle workload</h3><div class="ratib-metric"><span class="ratib-metric__val">2.8k</span><span class="ratib-metric__chart ratib-metric__chart--bars" aria-hidden="true"></span></div><p>Workers in non-terminal stages across connected agencies.</p></article>
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops">normalized demand index</p><h3>Throughput vs baseline</h3><div class="ratib-metric"><span class="ratib-metric__val">+31%</span><span class="ratib-metric__note">QoQ</span></div><p>Comparable velocity after seasonal adjustment—not vanity growth.</p></article>
                    <article class="ratib-analytics-card"><p class="ratib-analytics-card__stamp ratib-mono-ops">engine attribution · 7d</p><h3>Automated transition share</h3><div class="ratib-metric"><span class="ratib-metric__val">76%</span><span class="ratib-metric__note">engine-led hops</span></div><p>Remainder explicit HITL—policy requires human gates.</p></article>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-ops-visibility" id="operational">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow">Operational visibility</p>
                    <h2 class="ratib-section__title">What mission control actually shows</h2>
                    <p class="ratib-section__sub">Live-style aggregates you would expect in a deployed ops console: SLA posture, queue depth, automation outcomes, finance connector ACKs, and streamed events.</p>
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
                    <div class="ratib-trust-band__item"><span class="ratib-trust-band__k">API edge</span><span class="ratib-trust-band__v">REST · TLS 1.3 · scoped keys</span></div>
                    <div class="ratib-trust-band__item"><span class="ratib-trust-band__k">Regions</span><span class="ratib-trust-band__v">ME primary · EU replication optional</span></div>
                    <div class="ratib-trust-band__item"><span class="ratib-trust-band__k">Data plane</span><span class="ratib-trust-band__v">encrypted · tenant-scoped · audit trail</span></div>
                    <div class="ratib-trust-band__item"><span class="ratib-trust-band__k">Identity</span><span class="ratib-trust-band__v">RBAC · SSO-ready · session revocation</span></div>
                    <div class="ratib-trust-band__item"><span class="ratib-trust-band__k">Workflow history</span><span class="ratib-trust-band__v">immutable stage commits · correlation ids</span></div>
                    <div class="ratib-trust-band__item"><span class="ratib-trust-band__k">Continuity</span><span class="ratib-trust-band__v">operational backups · replayable events</span></div>
                </div>
            </div>
        </section>

        <section class="ratib-section ratib-api-strip" id="api">
            <div class="ratib-container ratib-api-strip__inner">
                <div>
                    <p class="ratib-eyebrow">Developers</p>
                    <h2 class="ratib-api-strip__title">APIs for the recruitment operating system</h2>
                    <p class="ratib-api-strip__sub">Versioned integration endpoints for HRIS, ERP, verification vendors, and internal data lakes—authenticated, rate-aware, observable.</p>
                </div>
                <a href="#contact" class="ratib-btn ratib-btn--outline">Request API access</a>
            </div>
        </section>

        <section class="pricing-section ratib-pricing-saas" id="programs">
            <div class="ratib-container">
                <header class="ratib-section__head">
                    <p class="ratib-eyebrow">Pricing</p>
                    <h2 class="ratib-section__title">Plans that scale with your agency footprint</h2>
                    <p class="ratib-section__sub">Transparent tiers for evaluation, production, and enterprise procurement teams.</p>
                </header>
                <div class="pricing-row pricing-row--three">
            <div class="price-card price-card-starter">
                <span class="card-badge card-badge--muted">Evaluate</span>
                <div class="card-plan">Starter</div>
                <div class="card-subtitle">Discovery &amp; Pro onboarding</div>
                <p class="card-price-saas">Custom scope</p>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <li><i class="fas fa-check"></i> Pro plan consultation</li>
                    <li><i class="fas fa-check"></i> Workspace readiness review</li>
                    <li><i class="fas fa-check"></i> Integration guidance</li>
                    <li><i class="fas fa-check"></i> Dedicated success touchpoints</li>
                </ul>
                <a href="#register" class="btn-register btn-register-starter js-open-register" data-register-plan="pro" data-register-amount="" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> Talk to solutions</a>
            </div>
            <div class="price-card gold price-card--featured">
                <span class="card-badge">Popular</span>
                <div class="card-plan">Business <span class="card-plan-note">list $<?php echo number_format((float)$goldListPriceYear1, 0); ?></span></div>
                <div class="card-subtitle">Branded agency portal · Gold tier</div>
                <div class="plan-year-wrap">
                    <div class="plan-year-buttons">
                        <button type="button" class="year-btn gold-year-btn year-btn-card year-btn-neutral" data-years="0" data-price="<?php echo (float)$goldTestPriceMonth; ?>">Monthly<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$goldListPriceMonth, 2); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceMonth, 2); ?></span></span></button>
                        <button type="button" class="year-btn gold-year-btn year-btn-card year-btn-gold-active active" data-years="1" data-price="<?php echo (float)$goldTestPriceYear1; ?>">1 Year<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
                <p class="card-price-old" id="goldOldPrice">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></p>
                <p class="card-price" id="goldPrice">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?> <span id="goldPriceLabel">for 1 year</span></p>
                <span class="card-discount">50% Discount</span>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <li><i class="fas fa-check"></i> Candidate & document management</li>
                    <li><i class="fas fa-check"></i> Your branded portal</li>
                    <li><i class="fas fa-check"></i> 20 users</li>
                    <li><i class="fas fa-check"></i> E-invoice system</li>
                    <li><i class="fas fa-check"></i> Standard support</li>
                    <li><i class="fas fa-check"></i> Managed infrastructure &amp; SSL</li>
                    <li><i class="fas fa-check"></i> Admin control panel</li>
                </ul>
                <a href="#register" id="goldRegisterBtn" class="btn-register js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> Deploy Business workspace</a>
            </div>
            <div class="price-card platinum">
                <span class="card-badge">50% Off</span>
                <div class="card-plan">Enterprise <span class="card-plan-note">list $<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span></div>
                <div class="card-subtitle">Mission-critical programs · Platinum tier</div>
                <div class="plan-year-wrap">
                    <div class="plan-year-buttons">
                        <button type="button" class="year-btn platinum-year-btn year-btn-card year-btn-neutral" data-years="0" data-price="<?php echo (float)$platinumTestPriceMonth; ?>">Monthly<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$platinumListPriceMonth, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceMonth, 0); ?></span></span></button>
                        <button type="button" class="year-btn platinum-year-btn year-btn-card year-btn-platinum-active active" data-years="1" data-price="<?php echo (float)$platinumTestPriceYear1; ?>">1 Year<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
                <p class="card-price-old" id="platinumOldPrice">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></p>
                <p class="card-price" id="platinumPrice">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?> <span id="platinumPriceLabel">for 1 year</span></p>
                <span class="card-discount">50% Discount</span>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <li><i class="fas fa-check"></i> All Business features</li>
                    <li><i class="fas fa-check"></i> Unlimited users</li>
                    <li><i class="fas fa-check"></i> Priority support</li>
                    <li><i class="fas fa-check"></i> Advanced analytics</li>
                    <li><i class="fas fa-check"></i> Dedicated setup</li>
                    <li><i class="fas fa-check"></i> Managed infrastructure &amp; SSL</li>
                    <li><i class="fas fa-check"></i> Admin control panel</li>
                    <li><i class="fas fa-check"></i> Custom integrations</li>
                </ul>
                <a href="#register" id="platinumRegisterBtn" class="btn-register js-open-register" data-register-plan="platinum" data-register-amount="<?php echo (float)($plans['platinum']['amount'] ?? $platinumTestPriceYear1); ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> Deploy Enterprise workspace</a>
            </div>
        </div>
            </div>
        </section>

        <section class="register-section register-section-hidden ratib-register-wrap" id="register">
        <div class="ratib-info">
            <h2><i class="fas fa-info-circle me-2 register-info-icon"></i>What is Ratib Program?</h2>
            <p>Ratib is a professional platform for recruitment agencies and companies in worker-sending countries. Manage candidates, contracts, and compliance in one place.</p>
            <ul class="checklist">
                <li><i class="fas fa-check-circle"></i><span><strong>Recruitment management</strong> — Handle workers and candidates efficiently</span></li>
                <li><i class="fas fa-check-circle"></i><span><strong>Pro plan</strong> — Your own branded agency portal</span></li>
                <li><i class="fas fa-check-circle"></i><span><strong>Worker-sending countries</strong> — Bangladesh, Uganda, Kenya, Philippines, and more</span></li>
                <li><i class="fas fa-check-circle"></i><span><strong>Contracts & compliance</strong> — Track documents and meet regulations</span></li>
                <li><i class="fas fa-check-circle"></i><span><strong>Simple onboarding</strong> — Register your agency and we'll set you up</span></li>
                <li><i class="fas fa-check-circle"></i><span><strong>Document tracking</strong> — Licenses, visas, medical reports in one dashboard</span></li>
                <li><i class="fas fa-check-circle"></i><span><strong>Reporting & analytics</strong> — Track placements, status, and performance</span></li>
            </ul>
        </div>
        <div class="form-card">
            <h1><i class="fas fa-building me-2"></i>Register Your Agency</h1>
            <p class="subtitle">Request <?php echo htmlspecialchars($planLabel); ?> plan access<?php if ($planAmount): ?> — $<?php echo number_format($planAmount); ?><?php if ($years !== null): ?><?php if ((int)$years === 0): ?> per month<?php elseif ((int)$years > 0): ?> for <?php echo (int)$years; ?> year<?php echo (int)$years > 1 ? 's' : ''; ?><?php else: ?> setup<?php endif; ?><?php else: ?> setup<?php endif; ?><?php endif; ?>. We will review and contact you.</p>
            <div class="mb-3">
                <label class="form-label">Choose Plan</label>
                <p class="small mb-2 form-plan-hint"><i class="fas fa-info-circle me-1"></i>Select <strong>Gold (Business)</strong> or <strong>Platinum (Enterprise)</strong> to see the payment summary for your plan.</p>
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
                        <i class="fas fa-receipt me-2 payment-placeholder-icon"></i><strong>Pricing summary</strong> — Select <strong>Business (Gold)</strong> or <strong>Enterprise (Platinum)</strong> at the top of this form to see plan totals here before you submit.
                    </div>
                </div>
                <!-- Payment block: always in DOM; shown only for Gold/Platinum (JS toggles visibility) -->
                <div id="paymentBlockWrap" class="payment-block-wrap mb-4 <?php echo ($plan !== 'pro' && $planAmount) ? '' : 'is-hidden'; ?>">
                    <!-- Payment Summary -->
                    <div class="mb-4 payment-summary-box payment-summary-panel">
                        <h4 class="payment-summary-title"><i class="fas fa-receipt me-2"></i>Payment Summary</h4>
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
                    <p class="small mb-0 payment-summary-footnote"><i class="fas fa-file-invoice me-2 payment-summary-footnote-icon"></i>Submit your request below. We will contact you about payment after review.</p>
                </div>
                
                <button type="submit" class="btn btn-primary btn-submit" id="btnSubmit"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
            </form>
        </div>
    </section>

        <section class="ratib-final-cta" aria-labelledby="ratib-final-cta-title">
            <div class="ratib-final-cta__bg" aria-hidden="true"></div>
            <div class="ratib-container ratib-final-cta__inner">
                <h2 id="ratib-final-cta-title" class="ratib-final-cta__title">Put production-grade recruitment infrastructure online</h2>
                <p class="ratib-final-cta__sub">Event orchestration, workforce telemetry, and ledger-backed billing on one deployed plane—built for agencies already running at scale.</p>
                <div class="ratib-final-cta__actions">
                    <a href="#register" class="ratib-btn ratib-btn--primary ratib-btn--lg js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1">Start agency infrastructure</a>
                    <a href="mailto:ratibsrar@gmail.com?subject=RATIB%20platform%20demo%20request" class="ratib-btn ratib-btn--outline ratib-btn--lg">Book platform demo</a>
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
                <p>Enterprise recruitment operating system — multi-agency workforce intelligence, automation, and real-time tracking.</p>
            </div>
            <div class="ratib-footer-col">
                <h4>Platform</h4>
                <ul>
                    <li><a href="#platform">Overview</a></li>
                    <li><a href="#how-it-works">How it works</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#tracking">Tracking</a></li>
                    <li><a href="#operational">Operational visibility</a></li>
                    <li><a href="#programs">Pricing</a></li>
                    <li><a href="#api">APIs</a></li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="#solutions">Solutions</a></li>
                    <li><a href="#agencies">Agencies</a></li>
                    <li><a href="#operational">Visibility</a></li>
                    <li><a href="#video">Demo</a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>">Customer portal</a></li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4>Support</h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/login.php'); ?>">Support tickets</a></li>
                    <li><a href="https://wa.me/966599863868" target="_blank" rel="noopener noreferrer">WhatsApp</a></li>
                    <li><a href="tel:+966599863868">+966 59 986 3868</a></li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4>Legal</h4>
                <ul>
                    <li><a href="#register">Service registration</a></li>
                    <li><a href="mailto:ratibsrar@gmail.com">ratibsrar@gmail.com</a></li>
                </ul>
            </div>
            <div class="ratib-footer-col ratib-footer-enterprise__infra">
                <h4>Infrastructure</h4>
                <p class="ratib-footer-enterprise__infra-copy">Managed cloud, TLS, isolated tenants, and compliance-oriented audit trails.</p>
                <div class="ratib-footer-social">
                    <a href="https://wa.me/966599863868" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="mailto:ratibsrar@gmail.com" aria-label="Email"><i class="fas fa-envelope"></i></a>
                </div>
                <div class="footer-subscribe ratib-footer-newsletter">
                    <label class="ratib-footer-newsletter__label" for="footerEmail">Updates</label>
                    <input type="email" placeholder="Work email" id="footerEmail" name="footer_email" autocomplete="email" aria-label="Email for newsletter">
                    <button type="button" class="btn-sub" id="footerSubscribe">Subscribe</button>
                </div>
            </div>
        </div>
        <div class="ratib-footer-system-strip">
            <div class="ratib-container ratib-footer-system-strip__inner">
                <span class="ratib-footer-system-strip__item"><span class="ratib-mono-tag">uptime</span> target 99.95% SLA · synthetic checks</span>
                <span class="ratib-footer-system-strip__item"><span class="ratib-mono-tag">requests</span> API gateway · rate limits · idempotent writes</span>
                <span class="ratib-footer-system-strip__item"><span class="ratib-mono-tag">events</span> orchestrator · audit · replay-safe logs</span>
            </div>
        </div>
        <div class="ratib-footer-enterprise__bottom">
            <div class="ratib-container ratib-footer-enterprise__bottom-inner">
                <span>&copy; <?php echo date('Y'); ?> RATIB — Ratib Software Foundation for Information Technology</span>
                <span class="ratib-footer-enterprise__loc"><i class="fas fa-location-dot" aria-hidden="true"></i> Riyadh, Saudi Arabia</span>
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
    <script type="application/json" id="ratib-home-bootstrap"><?php echo json_encode($ratibHomeBootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/home-page.js?v=<?php echo $ratibHomeJsV; ?>"></script>

    <!-- Chat Widget - Auto-answer support -->
    <button class="chat-widget-button" id="chatWidgetButton" aria-label="Open Chat"><i class="fas fa-comments"></i></button>
    <div class="chat-widget-container" id="chatWidgetContainer">
        <div class="chat-widget-header">
            <div class="chat-widget-header-info">
                <div class="chat-widget-header-avatar" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i></div>
                <div class="chat-widget-header-text"><h3>Ratib Assistant</h3><p class="online">Help guides &amp; live support</p></div>
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


