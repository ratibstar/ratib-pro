<?php
/**
 * Partner portal sign-in (magic token paste or agency ID + password).
 */
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'Partner portal sign-in';
$v = time();
$pageCss = [
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [asset('js/partnerships/partner-portal-login.js') . '?v=' . $v];
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<div class="partner-portal-login-wrap">
    <div class="glass-card partner-portal-login-card">
        <h1 class="partner-portal-login-title"><span aria-hidden="true">🌍</span> Partner portal</h1>
        <p class="partner-portal-login-lead">Sign in with the secure link your office sent you, or use agency ID and password if enabled.</p>
        <?php if (!empty($_GET['err'])): ?>
            <p class="partner-portal-login-err" role="alert">That link is invalid or portal access is disabled. Contact your office.</p>
        <?php endif; ?>

        <form id="ppLoginForm" class="partner-portal-login-form">
            <label class="partner-portal-label">Email (same as on file at the office)</label>
            <input type="email" id="ppEmail" name="email" autocomplete="username" class="partner-portal-input" placeholder="you@agency.com">

            <label class="partner-portal-label">Password</label>
            <input type="password" id="ppPassword" name="password" class="partner-portal-input" autocomplete="current-password" placeholder="Partner portal password">

            <p class="partner-portal-or">Other ways to sign in</p>

            <label class="partner-portal-label">Access token (from bookmark link)</label>
            <input type="text" id="ppToken" name="token" autocomplete="off" class="partner-portal-input" placeholder="Paste token only if you were sent a link">

            <p class="partner-portal-or">or</p>

            <label class="partner-portal-label">Agency ID + password</label>
            <input type="number" id="ppAgencyId" name="agency_id" class="partner-portal-input" placeholder="Numeric ID" min="1">

            <button type="submit" class="neon-btn partner-portal-submit">Sign in</button>
        </form>

        <p id="ppLoginMsg" class="partner-portal-login-msg" hidden></p>

        <p class="partner-portal-login-foot">
            <a href="<?php echo htmlspecialchars(pageUrl('login.php'), ENT_QUOTES, 'UTF-8'); ?>">Staff login</a>
        </p>
    </div>
</div>

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
