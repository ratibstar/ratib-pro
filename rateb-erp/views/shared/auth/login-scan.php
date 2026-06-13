<?php
/** @var string $token */
/** @var bool $tokenValid */
/** @var string $csrf */
?>
<div class="text-center mb-3">
    <i class="fas fa-barcode fa-2x text-primary"></i>
    <h2 class="h5 mt-2"><?php echo __('barcode_scan_title'); ?></h2>
    <?php if (!$tokenValid) { ?>
    <p class="text-danger small"><?php echo __('barcode_pair_expired'); ?></p>
    <a href="<?php echo rateb_url('login'); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('back_to_login'); ?></a>
    <?php } else { ?>
    <p class="text-muted small"><?php echo __('barcode_scan_hint'); ?></p>
    <?php } ?>
</div>

<?php if ($tokenValid) { ?>
<form method="post" action="<?php echo rateb_url('api/login-barcode-pair'); ?>" id="scan-submit-form" class="mb-3">
    <input type="hidden" name="action" value="submit">
    <input type="hidden" name="token" value="<?php echo Rateb\App\Core\View::escape($token); ?>">
    <div class="mb-3">
        <label class="form-label" for="scan-barcode"><?php echo __('login_barcode'); ?></label>
        <input type="text" class="form-control form-control-lg font-monospace text-center" id="scan-barcode" name="barcode"
            autofocus autocomplete="off" placeholder="<?php echo __('login_barcode_placeholder'); ?>">
    </div>
    <button type="submit" class="btn btn-primary w-100">
        <i class="fas fa-check me-1"></i> <?php echo __('barcode_scan_submit'); ?>
    </button>
</form>
<div id="scan-status" class="alert d-none" role="status"></div>
<script>
window.RATEB_SCAN = {
    token: <?php echo json_encode($token, JSON_UNESCAPED_UNICODE); ?>,
    apiPair: <?php echo json_encode(rateb_url('api/login-barcode-pair'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
</script>
<script src="<?php echo rateb_asset('js/erp-login-scan.js'); ?>"></script>
<?php } ?>
