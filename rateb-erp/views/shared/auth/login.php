<?php
/** @var string $csrf */
/** @var string $loginError */
?>
<?php if (!empty($loginError)) { ?>
<div class="alert alert-danger rateb-flash mb-3" role="alert"><?php echo Rateb\App\Core\View::escape($loginError); ?></div>
<?php } ?>
<div class="mb-3 text-center">
    <p class="text-muted small mb-2"><?php echo __('login_method'); ?></p>
    <div class="login-method-toggle" role="group" aria-label="<?php echo __('login_method'); ?>">
        <button type="button" class="login-method-btn active" data-method="password" aria-pressed="true">
            <i class="fas fa-user-lock" aria-hidden="true"></i> <?php echo __('login_method_password'); ?>
        </button>
        <button type="button" class="login-method-btn" data-method="barcode" aria-pressed="false">
            <i class="fas fa-qrcode" aria-hidden="true"></i> <?php echo __('login_method_barcode'); ?>
        </button>
    </div>
</div>

<form method="post" action="<?php echo rateb_url('login'); ?>" id="password-form" class="login-panel" novalidate>
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <?php if (!empty($branchPortal)) { ?>
    <input type="hidden" name="branch_id" value="<?php echo (int) ($branchPortal['id'] ?? 0); ?>">
    <?php if (!empty($branchPortal['company_slug']) && !empty($branchPortal['code'])) { ?>
    <input type="hidden" name="company" value="<?php echo Rateb\App\Core\View::escape((string) $branchPortal['company_slug']); ?>">
    <input type="hidden" name="branch" value="<?php echo Rateb\App\Core\View::escape((string) $branchPortal['code']); ?>">
    <?php } ?>
    <div class="alert alert-info py-2 small mb-3">
        <i class="fas fa-store me-1"></i>
        <?php echo __('branch_portal_login_hint', [
            'branch' => (string) ($branchPortal['name'] ?? ''),
            'company' => (string) ($branchPortal['company_name'] ?? ''),
        ]); ?>
    </div>
    <?php } ?>
    <?php if (!empty($next)) { ?>
    <input type="hidden" name="next" value="<?php echo Rateb\App\Core\View::escape((string) $next); ?>">
    <?php } ?>
    <?php if (!empty($agencyLoginHint)) { ?>
    <div class="alert alert-secondary py-2 small mb-3">
        <i class="fas fa-info-circle me-1"></i>
        <?php echo Rateb\App\Core\View::escape((string) $agencyLoginHint); ?>
    </div>
    <?php } ?>
    <p class="text-center text-muted small mb-3"><?php echo __('unified_login_hint'); ?></p>
    <div class="mb-3">
        <label class="form-label" for="email"><?php echo __('login_email'); ?></label>
        <input type="text" class="form-control" id="email" name="email" required autocomplete="username" placeholder="admin">
    </div>
    <div class="mb-3">
        <label class="form-label" for="password"><?php echo __('password'); ?></label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="<?php echo !empty($agencyLoginHint) ? 'off' : 'current-password'; ?>"<?php echo !empty($agencyLoginHint) ? ' placeholder="123456"' : ''; ?>>
    </div>
    <?php require __DIR__ . '/_remember.php'; ?>
    <button type="submit" class="btn btn-primary w-100"><?php echo __('login'); ?></button>
    <p class="mt-3 mb-0 text-center"><a href="<?php echo rateb_url('password/forgot'); ?>"><?php echo __('password_forgot'); ?></a></p>
</form>

<div id="barcode-form" class="login-panel text-center d-none">
    <div class="barcode-this-device barcode-login-panel mb-3">
        <h3 class="h5 mb-2"><i class="fas fa-laptop" aria-hidden="true"></i> <?php echo __('barcode_this_device_title'); ?></h3>
        <p class="text-muted small mb-3"><?php echo __('barcode_this_device_hint'); ?></p>
        <form method="post" action="<?php echo rateb_url('login/barcode'); ?>" id="barcode-login-form">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <label class="form-label visually-hidden" for="barcode-input"><?php echo __('login_barcode'); ?></label>
            <input type="text" class="form-control text-center font-monospace mb-2" name="barcode" id="barcode-input"
                placeholder="<?php echo Rateb\App\Core\View::escape(__('login_barcode_placeholder')); ?>"
                autocomplete="off" autocapitalize="characters" spellcheck="false" inputmode="text">
            <button type="submit" class="btn btn-primary w-100"><?php echo __('login_with_barcode'); ?></button>
        </form>
        <button type="button" class="btn btn-outline-info btn-sm mt-2 w-100" id="barcode-webcam-start">
            <i class="fas fa-camera" aria-hidden="true"></i> <?php echo __('barcode_start_camera'); ?>
        </button>
        <div id="barcode-webcam-viewport" class="barcode-webcam-viewport mt-2 d-none" aria-label="Camera scanner"></div>
    </div>

    <div id="barcode-desktop-panel" class="barcode-login-panel">
        <div class="barcode-scan-panel mb-3">
            <i class="fas fa-mobile-alt text-info icon-3em mb-2" aria-hidden="true"></i>
            <h3 class="h5 mb-2"><?php echo __('barcode_pair_computer_title'); ?></h3>
            <p class="text-muted mb-0 small"><?php echo __('barcode_pair_hint'); ?></p>
        </div>
        <div class="barcode-open-phone-box mb-2" id="barcode-pair-phone-box">
            <p class="small text-muted mb-2"><?php echo __('barcode_scan_qr_phone'); ?></p>
            <div id="barcode-pair-qr" class="barcode-login-url-qr" aria-label="QR code to open phone scanner"></div>
            <p class="barcode-pair-waiting small mt-2 mb-0" id="barcode-pair-waiting">
                <i class="fas fa-spinner fa-spin" aria-hidden="true"></i> <?php echo __('barcode_pair_waiting'); ?>
            </p>
        </div>
    </div>

    <div id="barcode-mobile-hint" class="barcode-login-panel d-none">
        <p class="text-muted small mb-2"><?php echo __('barcode_mobile_hint'); ?></p>
        <a id="barcode-mobile-scan-link" class="btn btn-outline-primary btn-sm mb-2" href="#"><?php echo __('barcode_open_scanner'); ?></a>
        <p class="text-muted small mb-0 mt-2"><?php echo __('barcode_mobile_desktop_hint'); ?></p>
    </div>

    <div id="barcode-status" class="barcode-status d-none mt-2" role="status"></div>
</div>

<script>
window.RATEB_LOGIN_BARCODE = {
    apiPair: <?php echo json_encode(rateb_url('api/login-barcode-pair'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    scanPage: <?php echo json_encode(rateb_url('login/scan'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    qrImageBase: <?php echo json_encode(rateb_url('scan/qr'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    home: <?php echo json_encode(rateb_url('admin'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    html5Qr: <?php echo json_encode(rateb_html5_qrcode_js(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    scannerJs: <?php echo json_encode(rateb_asset('js/erp-qr-scanner.js'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
</script>
<?php
$loginBarcodeJs = defined('RATEB_ROOT') ? RATEB_ROOT . '/public/assets/js/erp-login-barcode.js' : '';
if ($loginBarcodeJs !== '' && is_file($loginBarcodeJs)) {
    echo '<script>', file_get_contents($loginBarcodeJs), '</script>';
}
?>
<script>
(function () {
    // Prefer the token rendered with this page (after err=csrf|session recovery).
    // Only sync from meta when it is non-empty and longer than 16 chars.
    function syncLoginCsrf() {
        var meta = document.querySelector('meta[name="rateb-csrf"]');
        var token = meta ? (meta.getAttribute('content') || '') : '';
        if (!token || token.length < 16) return;
        document.querySelectorAll('#password-form input[name="_csrf"], #barcode-login-form input[name="_csrf"]').forEach(function (inp) {
            inp.value = token;
        });
    }
    syncLoginCsrf();
    document.querySelectorAll('#password-form, #barcode-login-form').forEach(function (form) {
        form.addEventListener('submit', syncLoginCsrf, true);
    });
})();
</script>
