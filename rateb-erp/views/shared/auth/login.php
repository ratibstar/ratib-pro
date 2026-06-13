<?php
/** @var string $csrf */
?>
<div class="mb-3">
    <label class="form-label" for="login-method"><?php echo __('login_method'); ?></label>
    <select class="form-select" id="login-method" name="login_method">
        <option value="password" selected><?php echo __('login_method_password'); ?></option>
        <option value="barcode"><?php echo __('login_method_barcode'); ?></option>
    </select>
</div>

<form method="post" action="<?php echo rateb_url('login'); ?>" id="password-form" class="login-panel">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <p class="text-center text-muted small mb-3"><?php echo __('unified_login_hint'); ?></p>
    <div class="mb-3">
        <label class="form-label" for="email"><?php echo __('login_email'); ?></label>
        <input type="email" class="form-control" id="email" name="email" required autocomplete="username">
    </div>
    <div class="mb-3">
        <label class="form-label" for="password"><?php echo __('password'); ?></label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary w-100"><?php echo __('login'); ?></button>
    <p class="mt-3 mb-0 text-center"><a href="<?php echo rateb_url('password/forgot'); ?>"><?php echo __('password_forgot'); ?></a></p>
</form>

<div id="barcode-form" class="login-panel d-none">
    <p class="text-center text-muted small mb-3"><?php echo __('barcode_login_hint'); ?></p>

    <div id="barcode-desktop-panel">
        <div class="text-center mb-3">
            <div id="barcode-pair-qr" class="rateb-login-qr-wrap mx-auto"></div>
            <p id="barcode-pair-waiting" class="small text-muted mt-2 d-none"><?php echo __('barcode_pair_waiting'); ?></p>
        </div>
        <p class="small text-center text-muted"><?php echo __('barcode_pair_hint'); ?></p>
    </div>

    <hr class="my-3">

    <form method="post" action="<?php echo rateb_url('login/barcode'); ?>" id="barcode-direct-form">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <div class="mb-3">
            <label class="form-label" for="barcode-input"><?php echo __('login_barcode'); ?></label>
            <input type="text" class="form-control font-monospace text-center" id="barcode-input" name="barcode"
                autocomplete="off" inputmode="text" placeholder="<?php echo __('login_barcode_placeholder'); ?>">
        </div>
        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-barcode me-1"></i> <?php echo __('login_with_barcode'); ?>
        </button>
    </form>
    <div id="barcode-status" class="d-none small mt-2"></div>
</div>

<script>
window.RATEB_LOGIN_BARCODE = {
    apiPair: <?php echo json_encode(rateb_url('api/login-barcode-pair'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    scanPage: <?php echo json_encode(rateb_url('login/scan'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    home: <?php echo json_encode(rateb_url('admin'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
</script>
<script src="<?php echo rateb_asset('js/erp-login-barcode.js'); ?>"></script>
