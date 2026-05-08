<?php
/**
 * Public: Home / landing page — English, layout like ratib.sa reference.
 * EN: Prepares server-side values (plans/currency/assets), renders page sections, and bootstraps JS config.
 * AR: يجهّز قيم السيرفر (الخطط/العملة/الأصول)، ويعرض أقسام الصفحة، ثم يمرر إعدادات JavaScript.
 */
require_once __DIR__ . '/../includes/config.php';

// Prevent stale HTML caching so pricing updates appear immediately.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}
// Force a fresh URL for country-context registration links to avoid stale edge cache variants.
$__isRegisterOpen = isset($_GET['open']) && trim((string) ($_GET['open'] ?? '')) === 'register';
$__hasCountryCtx = (
    trim((string) ($_GET['country_code'] ?? '')) !== '' ||
    trim((string) ($_GET['country_name'] ?? '')) !== '' ||
    trim((string) ($_GET['country_slug'] ?? '')) !== ''
);
if (!headers_sent() && $__isRegisterOpen && $__hasCountryCtx && !isset($_GET['cb'])) {
    $qs = $_GET;
    $qs['cb'] = (string) time();
    $target = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/pages/home.php');
    $redirectUrl = $target . '?' . http_build_query($qs);
    header('Location: ' . $redirectUrl, true, 302);
    exit;
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
$goldTestPriceYear1 = 650;
$goldTestPriceMonth = 54;
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
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title>RATIB — Recruitment Automation &amp; Tracking Intelligence Base | Ratib Recruitment Program</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/chat-widget.css">
    <?php $ratibHomeCssV = (int) (@filemtime(__DIR__ . '/../css/pages/home-public.css') ?: time()); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-public.css?v=<?php echo $ratibHomeCssV; ?>">
</head>
<body>

    <header class="header">
        <div class="header-left">
            <a href="tel:+966599863868" class="phone"><i class="fas fa-phone-alt"></i> +966 59 986 3868</a>
            <a href="#contact">Contact Us</a>
            <a href="https://wa.me/966599863868" target="_blank" rel="noopener noreferrer" class="live-status" title="Chat on WhatsApp">
                <div class="live-dots"><span></span><span></span><span></span></div>
                <span>Live via WhatsApp</span>
            </a>
        </div>
        <div class="header-center">
            <a href="<?php echo htmlspecialchars($baseUrl . '/pages/home.php'); ?>" class="logo">
                <img src="<?php echo htmlspecialchars($baseUrl . '/assets/ratib-logo.svg?v=3'); ?>" alt="Ratib Company — Ratib Software Foundation for Information Technology">
            </a>
            <div class="tagline">RATIB — Recruitment Automation &amp; Tracking Intelligence Base</div>
        </div>
        <div class="header-right">
            <a href="<?php echo htmlspecialchars($baseUrl . '/pages/home.php'); ?>" class="nav-link">Home</a>
            <a href="#programs" class="nav-link active">Our Programs <span class="badge-nav">Important</span></a>
            <a href="#register" class="nav-link js-scroll-register">Register</a>
            <a href="#video" class="nav-link">Video</a>
            <a href="#featured" class="nav-link">Features</a>
            <a href="#hosting" class="nav-link">Hosting</a>
            <a href="#payment" class="nav-link">Payment Methods</a>
            <a href="#support" class="nav-link">Technical Support</a>
            <a href="#contact-options" class="nav-link">Contact Options</a>
            <a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>" class="btn-client"><i class="fas fa-user"></i> Customer Portal</a>
        </div>
    </header>

    <!-- Animated Background Layers -->
    <div class="bg-animated"></div>
    <div class="bg-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="bg-hex"></div>
    <div class="bg-overlay"></div>

    <section class="hero-wrap">
        <div class="hero-text">
            <h1>Ratib <span>Recruitment</span> Program</h1>
            <p class="desc" style="margin-top: -0.2rem; color: #f1c40f; font-weight: 700;">RATIB = Recruitment Automation &amp; Tracking Intelligence Base</p>
            <p class="desc">RATIB (Recruitment Automation &amp; Tracking Intelligence Base) is a recruitment program for managing recruitment offices and companies with the electronic invoice system.</p>
            <a href="#programs" class="btn-prices"><i class="fas fa-tags"></i> Prices</a>
        </div>
    </section>

    <!-- Company Introduction Section -->
    <section class="company-intro">
        <h2>Welcome to Ratib — Your Trusted Partner in Recruitment Technology</h2>
        <p class="intro-text">
            RATIB (Recruitment Automation &amp; Tracking Intelligence Base) is a leading provider of innovative recruitment management solutions. 
            We specialize in creating powerful, user-friendly platforms that transform how recruitment agencies and companies manage 
            their operations, from candidate tracking to electronic invoicing.
        </p>
        <div class="highlight-box">
            <h3 class="company-highlight-title">
                <i class="fas fa-star company-highlight-icon"></i>
                Why Ratib Stands Out
            </h3>
            <p class="company-highlight-text">
                With over 15 years of combined experience in software development and recruitment industry expertise, 
                Ratib delivers cutting-edge solutions that streamline operations, reduce costs, and accelerate growth. 
                Our platform combines advanced technology with intuitive design, making complex recruitment processes simple and efficient.
            </p>
        </div>
        <div class="feature-list">
            <div class="feature-item">
                <i class="fas fa-rocket"></i>
                <h3>Innovation First</h3>
                <p>We continuously evolve our platform with the latest technologies to keep you ahead of the competition.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-shield-alt"></i>
                <h3>Secure & Reliable</h3>
                <p>Enterprise-grade security and 99.9% uptime guarantee ensure your data is always safe and accessible.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-headset"></i>
                <h3>Dedicated Support</h3>
                <p>Our expert team provides 24/7 support to help you succeed with personalized assistance whenever you need it.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-chart-line"></i>
                <h3>Proven Results</h3>
                <p>Join hundreds of successful agencies who have transformed their operations and increased efficiency with Ratib.</p>
            </div>
        </div>
    </section>

    <section class="featured-section" id="featured">
        <h2><i class="fas fa-star me-2"></i>Why Choose Ratib</h2>
        <div class="featured-row">
            <div class="featured-card">
                <?php if (!empty($galleryImages[0])): ?><img src="<?php echo htmlspecialchars($galleryImages[0]); ?>" alt="Professional Team" class="card-img" loading="lazy"><?php else: ?><div class="card-icon"><i class="fas fa-users-cog"></i></div><?php endif; ?>
                <h3>Professional Team</h3>
                <p>Professional team of programmers, designers, and specialists in electronic marketing and smart mobile applications.</p>
                <a href="#register" class="btn-more js-scroll-register">Learn More</a>
            </div>
            <div class="featured-card">
                <?php if (!empty($galleryImages[1])): ?><img src="<?php echo htmlspecialchars($galleryImages[1]); ?>" alt="Value & Service" class="card-img" loading="lazy"><?php else: ?><div class="card-icon"><i class="fas fa-tags"></i></div><?php endif; ?>
                <h3>Competitive Value</h3>
                <p>You will find lower prices elsewhere, but do they offer the same service? There may be hidden costs or missing features. Compare carefully.</p>
                <a href="#programs" class="btn-more">View Prices</a>
            </div>
            <div class="featured-card">
                <?php if (!empty($galleryImages[2])): ?><img src="<?php echo htmlspecialchars($galleryImages[2]); ?>" alt="24/7 Support" class="card-img" loading="lazy"><?php else: ?><div class="card-icon"><i class="fas fa-clock"></i></div><?php endif; ?>
                <h3>Around the Clock</h3>
                <p>Every day we work to ensure your software runs correctly and appropriately. Reliable support when you need it.</p>
                <a href="#support" class="btn-more">Learn More</a>
            </div>
        </div>
    </section>

    <section class="video-section" id="video">
        <h2><i class="fas fa-play-circle me-2"></i>How it works</h2>
        <p class="video-caption">Watch a short overview of the Ratib recruitment program.</p>
        <div class="video-wrap">
            <?php if ($videoExists): ?>
            <video controls preload="metadata" class="home-video-player">
                <source src="<?php echo htmlspecialchars($videoSrcRel, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                Your browser does not support the video tag. <a href="<?php echo htmlspecialchars($videoSrcRel, ENT_QUOTES, 'UTF-8'); ?>">Download the video</a>.
            </video>
            <?php else: ?>
            <div class="video-fallback-box">
                <i class="fas fa-video-slash fa-3x mb-3"></i>
                <p>Add an MP4 to <code>assets/</code> — recommended name: <code>video.mp4</code></p>
                <p class="small mb-0">Any <strong>.mp4</strong> file in the <code>assets</code> folder will be used automatically if <code>video.mp4</code> is not present.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="gallery-section" id="gallery">
        <h2><i class="fas fa-images me-2"></i>Gallery</h2>
        <?php if (!empty($galleryImages)): ?>
        <div class="gallery-grid">
            <?php foreach ($galleryImages as $img): ?>
            <img src="<?php echo htmlspecialchars($img); ?>" alt="Ratib Program" loading="lazy">
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="gallery-empty">
            <i class="fas fa-images fa-3x mb-3 gallery-empty-icon"></i>
            <p>Add images to <code>assets/images/</code> (jpg, png, webp, gif) to display them here.</p>
        </div>
        <?php endif; ?>
    </section>

    <section class="pricing-section" id="programs">
        <h2>Plans & Pricing</h2>
        <div class="pricing-row">
            <div class="price-card gold">
                <span class="card-badge">50% Off</span>
                <div class="card-plan">Gold $<?php echo number_format((float)$goldListPriceYear1, 0); ?></div>
                <div class="card-subtitle">Branded agency portal</div>
                <div class="plan-year-wrap">
                    <div class="plan-year-buttons">
                        <button type="button" class="year-btn gold-year-btn year-btn-card year-btn-neutral" data-years="0" data-price="<?php echo (float)$goldTestPriceMonth; ?>">Monthly<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$goldListPriceMonth, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceMonth, 0); ?></span></span></button>
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
                    <li><i class="fas fa-check"></i> Free Hosting with a domain</li>
                    <li><i class="fas fa-check"></i> Free for one year</li>
                    <li><i class="fas fa-check"></i> Admin control panel</li>
                </ul>
                <a href="#register" id="goldRegisterBtn" class="btn-register js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> Register</a>
            </div>
            <div class="price-card platinum">
                <span class="card-badge">50% Off</span>
                <div class="card-plan">Platinum $<?php echo number_format((float)$platinumListPriceYear1, 0); ?></div>
                <div class="card-subtitle">Full-featured solution</div>
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
                    <li><i class="fas fa-check"></i> All Gold features</li>
                    <li><i class="fas fa-check"></i> Unlimited users</li>
                    <li><i class="fas fa-check"></i> Priority support</li>
                    <li><i class="fas fa-check"></i> Advanced analytics</li>
                    <li><i class="fas fa-check"></i> Dedicated setup</li>
                    <li><i class="fas fa-check"></i> Free Hosting with a domain</li>
                    <li><i class="fas fa-check"></i> Free for one year</li>
                    <li><i class="fas fa-check"></i> Admin control panel</li>
                    <li><i class="fas fa-check"></i> Custom integrations</li>
                </ul>
                <a href="#register" id="platinumRegisterBtn" class="btn-register js-open-register" data-register-plan="platinum" data-register-amount="<?php echo (float)($plans['platinum']['amount'] ?? $platinumTestPriceYear1); ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> Register</a>
            </div>
        </div>
    </section>

    <section class="register-section register-section-hidden" id="register">
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
                <p class="small mb-2 form-plan-hint"><i class="fas fa-info-circle me-1"></i>Select <strong>Gold</strong> or <strong>Platinum</strong> to see the payment summary for your plan.</p>
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <button type="button" class="btn plan-btn-form plan-btn-pro" data-plan="pro" data-amount="" data-years="1"><i class="fas fa-star me-1"></i> Pro</button>
                    <button type="button" class="btn plan-btn-form plan-btn-gold" data-plan="gold" data-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-years="1"><i class="fas fa-crown me-1"></i> Gold <span class="promo-old">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?></span></button>
                    <button type="button" class="btn plan-btn-form plan-btn-platinum" data-plan="platinum" data-amount="<?php echo (float)$platinumTestPriceYear1; ?>" data-years="1"><i class="fas fa-gem me-1"></i> Platinum <span class="promo-old">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?></span></button>
                </div>
                <div id="formYearButtonsWrap" class="mb-2 <?php echo ($plan !== 'pro' && $planAmount) ? '' : 'is-hidden'; ?>">
                    <label class="form-label form-duration-label">Duration</label>
                    <div class="d-flex gap-2 flex-wrap" id="formYearButtons">
                        <button type="button" class="form-year-btn" data-years="0" data-price-gold="<?php echo (float)$goldTestPriceMonth; ?>" data-price-platinum="<?php echo (float)$platinumTestPriceMonth; ?>">Monthly<br><span class="form-year-price"><span class="promo-old">$<?php echo number_format((float)$goldListPriceMonth, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceMonth, 0); ?></span></span></button>
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
                        <i class="fas fa-receipt me-2 payment-placeholder-icon"></i><strong>Pricing summary</strong> — Select <strong>Gold</strong> or <strong>Platinum</strong> at the top of this form to see plan totals here before you submit.
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
                        <p class="small text-muted mb-0 mt-1 ratib-ngenius-currency-note">Gateway charge at checkout: <strong><?php echo htmlspecialchars($__gatewayCurrency, ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format($__gatewayTotal, 2); ?></strong> <span class="ratib-ngenius-rate-note">(USD × <?php echo htmlspecialchars(number_format($__gatewayRate, 2), ENT_QUOTES, 'UTF-8'); ?>)</span>.</p>
                        <p class="small text-muted mb-0 mt-1 ratib-ngenius-currency-note">You will complete payment in <strong><?php echo htmlspecialchars($__gatewayCurrency, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                        <?php endif; ?>
                        <?php } ?>
                    </div>
                    <p class="small mb-0 payment-summary-footnote"><i class="fas fa-file-invoice me-2 payment-summary-footnote-icon"></i>Submit your request below. We will contact you about payment after review.</p>
                </div>
                
                <button type="submit" class="btn btn-primary btn-submit" id="btnSubmit"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
            </form>
        </div>
    </section>

    <section class="pricing-section" id="hosting">
        <h2><i class="fas fa-server me-2"></i>Hosting</h2>
        <p class="home-centered-copy">We provide secure hosting for your agency portal. Each plan includes database hosting and SSL. Contact us for custom hosting needs.</p>
    </section>

    <section class="pricing-section" id="payment">
        <h2><i class="fas fa-credit-card me-2"></i>Payment Methods</h2>
        <p class="home-centered-copy home-centered-copy-tight">We coordinate payment after you register — bank transfer and other options are available once your request is reviewed.</p>
        <p class="home-centered-note">Use the <strong>Register Your Agency</strong> form above to choose your plan and submit your details.</p>
        <div class="payment-method-grid">
            <div class="payment-method-card">
                <i class="fas fa-university fa-3x mb-3 payment-method-icon"></i>
                <h3 class="payment-method-title">Bank Transfer</h3>
                <p class="payment-method-copy">Traditional bank transfer. Payment details provided after registration approval.</p>
                <a href="#register" class="btn btn-outline-light home-bank-register-btn js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1">
                    <i class="fas fa-arrow-right me-2"></i>Register First (Pay Later)
                </a>
            </div>
        </div>
    </section>

    <section class="support-highlight" id="support">
        <div class="support-card">
            <div class="support-card-left">
                <h3><i class="fas fa-headset me-2"></i>Technical Support</h3>
                <p><i class="fas fa-phone-alt me-2 support-contact-icon"></i><a href="tel:+966599863868">+966 59 986 3868</a></p>
                <p><i class="fas fa-map-marker-alt me-2 support-contact-icon"></i> Al-Kharj — King Fahd Road</p>
                <ul class="support-features">
                    <li><i class="fas fa-check-circle"></i> Fast response</li>
                    <li><i class="fas fa-check-circle"></i> Solves problems from the roots</li>
                    <li><i class="fas fa-check-circle"></i> 15+ years combined experience</li>
                </ul>
            </div>
            <a href="#contact" class="btn-support"><i class="fas fa-envelope me-2"></i>Contact Us</a>
        </div>
    </section>

    <section class="contact-options" id="contact-options">
        <h2><i class="fas fa-comments me-2"></i>Get in Touch</h2>
        <div class="contact-options-row">
            <div class="contact-option-card whatsapp">
                <div class="opt-icon"><i class="fab fa-whatsapp"></i></div>
                <h3>Start Instant Chat</h3>
                <p>Need a quick reply and faster response? Talk to us via WhatsApp.</p>
                <a href="https://wa.me/966599863868" target="_blank" rel="noopener noreferrer">Chat on WhatsApp</a>
            </div>
            <div class="contact-option-card ticket">
                <div class="opt-icon"><i class="fas fa-life-ring"></i></div>
                <h3>Open Ticket</h3>
                <p>Have an inquiry? Open a ticket and we will reply to you as soon as possible.</p>
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>">Customer Portal</a>
            </div>
            <div class="contact-option-card email">
                <div class="opt-icon"><i class="fas fa-envelope"></i></div>
                <h3>Contact Us</h3>
                <p>Send us an email and one of our sales staff will reply to you as soon as possible.</p>
                <a href="mailto:ratibsrar@gmail.com">ratibsrar@gmail.com</a>
            </div>
        </div>
    </section>

    <section class="pricing-section" id="contact">
        <h2><i class="fas fa-envelope me-2"></i>Contact Us</h2>
        <p class="home-centered-copy">Phone: <a href="tel:+966599863868" class="contact-accent-link">+966 59 986 3868</a> &nbsp;|&nbsp; WhatsApp: <a href="https://wa.me/966599863868" target="_blank" rel="noopener noreferrer" class="contact-whatsapp-link">Chat now</a> &nbsp;|&nbsp; Email: <a href="mailto:ratibsrar@gmail.com" class="contact-accent-link">ratibsrar@gmail.com</a>. You can also use the registration form above to request a callback.</p>
    </section>

    <footer class="main-footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/home.php'); ?>" class="logo">
                    <img src="<?php echo htmlspecialchars($baseUrl . '/assets/ratib-logo.svg?v=3'); ?>" alt="Ratib Company — Ratib Software Foundation for Information Technology">
                </a>
                <p>RATIB — Recruitment Automation &amp; Tracking Intelligence Base</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#payment">Payment Methods</a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>">Customer Portal</a></li>
                    <li><a href="#register" class="js-scroll-register">Register New Account</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Technical Support</h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/login.php'); ?>">Support Tickets</a></li>
                    <li><a href="#support">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Our Services</h4>
                <ul>
                    <li><a href="#programs">Ratib Recruitment Program</a></li>
                    <li><a href="#hosting">Shared Hosting</a></li>
                </ul>
            </div>
            <div class="footer-col footer-subscribe">
                <h4>Newsletter</h4>
                <input type="email" placeholder="Enter your email" id="footerEmail" aria-label="Email for newsletter">
                <button type="button" class="btn-sub" id="footerSubscribe">Subscribe</button>
                <p>Subscribe to our mailing list for exclusive offers.</p>
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


