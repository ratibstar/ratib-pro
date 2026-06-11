<form method="post" action="<?php echo rateb_url('password/reset/' . Rateb\App\Core\View::escape($token)); ?>">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <div class="mb-3">
        <label class="form-label" for="password"><?php echo __('password'); ?></label>
        <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password">
    </div>
    <div class="mb-3">
        <label class="form-label" for="password_confirm"><?php echo __('password_confirm'); ?></label>
        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary w-100"><?php echo __('password_reset_save'); ?></button>
</form>
