<?php
/**
 * Partner portal sign-in (username + password).
 * Lightweight shell — unified marketing home at / (no legacy /home mega-nav bootstrap).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/rateb-public-base-url.php';

$ppMagicLinkNotice = false;
if (!empty($_SESSION['partner_portal_flash_magic_link_failed'])) {
    $ppMagicLinkNotice = true;
    unset($_SESSION['partner_portal_flash_magic_link_failed']);
} elseif (!empty($_GET['err'])) {
    $ppMagicLinkNotice = true;
}

$baseUrl = rateb_public_site_base_url();
$marketingHome = function_exists('rateb_public_marketing_home_url')
    ? rateb_public_marketing_home_url($baseUrl)
    : rtrim($baseUrl, '/') . (function_exists('rateb_marketing_home_path') ? rateb_marketing_home_path() : '/');
$customerLoginUrl = rtrim($baseUrl, '/') . '/site/login';

$ratebPartnerPortalHomeChrome = false;

$pageTitle = 'Partner portal sign-in';
$v = time();
$pageCss = [
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partnerships-agency-detail.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [
    asset('js/partnerships/partner-portal-login.js') . '?v=' . $v,
];
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<header class="partner-portal-lite-header">
    <div class="container d-flex align-items-center justify-content-between py-3">
        <a href="<?php echo htmlspecialchars($marketingHome, ENT_QUOTES, 'UTF-8'); ?>" class="partner-portal-lite-brand">
            <img src="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/assets/rateb-logo.svg?v=6', ENT_QUOTES, 'UTF-8'); ?>" alt="RATEB" width="110" height="33">
        </a>
        <nav class="partner-portal-lite-nav d-flex align-items-center gap-3 flex-wrap">
            <a href="<?php echo htmlspecialchars($marketingHome, ENT_QUOTES, 'UTF-8'); ?>">Home</a>
            <a href="<?php echo htmlspecialchars($customerLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">Customer login</a>
            <span class="partner-portal-lite-nav__current" aria-current="page">Partner login</span>
        </nav>
    </div>
</header>

<div class="partner-portal-wrap partner-portal-login-page agency-detail-page">
    <div class="partner-portal-login-wrap">
        <div class="glass-card partner-portal-login-card">
            <h1 class="partner-portal-login-title"><span aria-hidden="true">🌍</span> Partner portal</h1>
            <p class="partner-portal-login-lead">Sign in with your username and password.</p>
            <?php if ($ppMagicLinkNotice): ?>
                <p class="partner-portal-login-notice" role="status">
                    If you opened an access link, it may be outdated or portal access was turned off. Sign in below with username and password, or ask your office for a new link.
                </p>
            <?php endif; ?>

            <form id="ppLoginForm" class="partner-portal-login-form">
                <label class="partner-portal-label">Username</label>
                <input type="text" id="ppUsername" name="username" autocomplete="username" class="partner-portal-input" placeholder="Agency ID, email, or partner name">

                <label class="partner-portal-label">Password</label>
                <input type="password" id="ppPassword" name="password" class="partner-portal-input" autocomplete="current-password" placeholder="Partner portal password">

                <button type="submit" class="neon-btn partner-portal-submit">Sign in</button>
            </form>

            <p id="ppLoginMsg" class="partner-portal-login-msg" hidden></p>

            <p class="partner-portal-login-foot">
                <a href="<?php echo htmlspecialchars(pageUrl('login.php'), ENT_QUOTES, 'UTF-8'); ?>">Staff login</a>
                ·
                <a href="<?php echo htmlspecialchars($marketingHome, ENT_QUOTES, 'UTF-8'); ?>">Back to rateb.sa</a>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
