<?php
/**
 * Partner portal sign-in (username + password).
 */
require_once __DIR__ . '/../includes/config.php';

$ppMagicLinkNotice = false;
if (!empty($_SESSION['partner_portal_flash_magic_link_failed'])) {
    $ppMagicLinkNotice = true;
    unset($_SESSION['partner_portal_flash_magic_link_failed']);
} elseif (!empty($_GET['err'])) {
    // Legacy redirects used ?err=1 — still supported; JS strips query so refresh does not repeat.
    $ppMagicLinkNotice = true;
}

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;

require_once __DIR__ . '/../includes/ratib-home-public-nav-bootstrap.php';
$ratibHomeNavHrefPrefix = function_exists('ratib_public_nav_marketing_home_prefix')
    ? ratib_public_nav_marketing_home_prefix($baseUrl)
    : $baseUrl . '/pages/home.php';
$ratibHomeHeaderPartnerIsCurrent = true;
$ratibPartnerPortalHomeChrome = true;
$ratibPartnerPortalNavFallbackCss = true;

$pageTitle = 'Partner portal sign-in';
$v = time();
$pageCss = [
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    $baseUrl . '/css/chat-widget.css',
    $baseUrl . '/css/pages/home-public.css?v=' . $ratibHomePublicCssQuery,
    $baseUrl . '/css/pages/ratib-mega-nav.css?v=' . $ratibMegaNavCssQuery,
    $baseUrl . '/css/pages/ratib-public-nav-brand.css?v=' . $ratibPublicNavBrandCssQuery,
    $baseUrl . '/css/pages/home-enterprise-calm.css?v=' . $ratibEnterpriseCalmCssQuery,
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partnerships-agency-detail.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [
    asset('js/pages/ratib-home-nav-chrome.js') . '?v=' . $ratibHomePublicCssQuery,
    asset('js/partnerships/partner-portal-login.js') . '?v=' . $v,
];
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<?php include __DIR__ . '/../includes/ratib-home-public-chrome-top.php'; ?>

<div class="partner-portal-wrap partner-portal-login-page agency-detail-page partner-portal-login-page--home-chrome">
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
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/ratib-home-public-footer.php'; ?>

<?php
$ratibPublicChatSkipCss = true;
require_once __DIR__ . '/../includes/chat-widget-public-footer.php';
?>
<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
