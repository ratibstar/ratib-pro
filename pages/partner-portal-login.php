<?php
/**
 * Partner portal sign-in (magic token paste or agency ID + password).
 */
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'Partner portal sign-in';
$v = time();
$pageCss = [
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partnerships-agency-detail.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [asset('js/partnerships/partner-portal-login.js') . '?v=' . $v];
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<div class="partner-portal-wrap partner-portal-login-page agency-detail-page">
    <header class="partner-portal-header-mega glass-card partner-portal-header-mega--login-only">
        <?php include __DIR__ . '/../includes/partner-portal-marketing-strip.php'; ?>
    </header>
    <div class="partner-portal-login-wrap">
        <div class="glass-card partner-portal-login-card">
            <h1 class="partner-portal-login-title"><span aria-hidden="true">🌍</span> Partner portal</h1>
            <p class="partner-portal-login-lead">Sign in with your username and password.</p>
            <?php if (!empty($_GET['err'])): ?>
                <p class="partner-portal-login-err" role="alert">That link is invalid or portal access is disabled. Contact your office.</p>
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

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
