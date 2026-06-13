<?php
/** @var string $csrf */
?>
<form method="post" action="<?php echo rateb_url('login/2fa'); ?>" class="login-panel">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <p class="text-muted small"><?php echo __('two_factor_hint'); ?></p>
    <div class="mb-3">
        <label class="form-label" for="code"><?php echo __('two_factor_code'); ?></label>
        <input type="text" class="form-control" id="code" name="code" required autocomplete="one-time-code" inputmode="numeric" pattern="[0-9A-Za-z]{6,8}">
    </div>
    <button type="submit" class="btn btn-primary w-100"><?php echo __('verify'); ?></button>
</form>
