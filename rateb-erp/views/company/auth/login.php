<form method="post" action="<?php echo rateb_url('company/login'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <div class="mb-3">
        <label class="form-label" for="email"><?php echo __('login_email'); ?></label>
        <input type="email" class="form-control" id="email" name="email" required autocomplete="username">
    </div>
    <div class="mb-3">
        <label class="form-label" for="password"><?php echo __('password'); ?></label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary w-100"><?php echo __('login'); ?></button>
    <p class="mt-3 mb-0 text-center"><a href="<?php echo rateb_url('password/forgot?portal=company'); ?>"><?php echo __('password_forgot'); ?></a></p>
</form>
