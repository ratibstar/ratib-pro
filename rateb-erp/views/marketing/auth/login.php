<?php /** @var string $csrf */ /** @var string $next */ ?>
<div class="rateb-auth-page-wrap">
    <div class="rateb-mkt-auth-card">
        <h1 class="h4 text-center mb-1"><?php echo __('cms_customer_login'); ?></h1>
        <p class="text-muted text-center small mb-3"><?php echo __('cms_customer_login_hint'); ?></p>
        <div class="alert alert-secondary py-2 small mb-4" role="status">
            <?php echo __('cms_staff_login_hint'); ?>
            <a class="fw-semibold d-inline-block ms-1" href="<?php echo Rateb\App\Core\View::escape((string) ($staffLoginUrl ?? rateb_url('login'))); ?>">
                <?php echo __('cms_staff_login_link'); ?>
            </a>
        </div>
        <form method="post" action="<?php echo rateb_url('site/login'); ?>" class="rateb-mkt-form">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php if (!empty($next)) { ?>
            <input type="hidden" name="next" value="<?php echo Rateb\App\Core\View::escape((string) $next); ?>">
            <?php } ?>
            <div class="mb-3">
                <label class="form-label" for="email"><?php echo __('login_email'); ?></label>
                <input type="email" class="form-control" id="email" name="email" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password"><?php echo __('password'); ?></label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                <label class="form-check-label" for="remember"><?php echo __('remember_me'); ?></label>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?php echo __('login'); ?></button>
            <p class="mt-3 mb-0 text-center small">
                <a href="<?php echo rateb_url('password/forgot'); ?>"><?php echo __('password_forgot'); ?></a>
            </p>
        </form>
        <hr class="my-4">
        <p class="text-center mb-2 small"><?php echo __('cms_no_account'); ?></p>
        <a href="<?php echo rateb_url('site/register'); ?>" class="btn btn-outline-primary w-100"><?php echo __('cms_register'); ?></a>
    </div>
</div>
