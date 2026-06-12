<form method="post" action="<?php echo rateb_url('password/forgot'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <div class="mb-3">
        <label class="form-label" for="email"><?php echo __('login_email'); ?></label>
        <input type="email" class="form-control" id="email" name="email" required autocomplete="username">
    </div>
    <button type="submit" class="btn btn-primary w-100"><?php echo __('password_reset_send'); ?></button>
    <p class="mt-3 mb-0 text-center">
        <a href="<?php echo rateb_url('login'); ?>"><?php echo __('back_to_login'); ?></a>
    </p>
</form>
