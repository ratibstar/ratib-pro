<?php /** @var string $csrf */ ?>
<section class="rateb-mkt-page-hero">
    <div class="container">
        <h1><?php echo __('cms_register'); ?></h1>
        <p class="text-muted mb-0"><?php echo __('cms_register_hint'); ?></p>
    </div>
</section>
<section class="rateb-mkt-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="rateb-mkt-auth-card">
                    <form method="post" action="<?php echo rateb_url('site/register'); ?>" class="rateb-mkt-form">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="company_name"><?php echo __('cms_company_name'); ?></label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="name"><?php echo __('cms_contact_name'); ?></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email"><?php echo __('login_email'); ?></label>
                            <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="phone"><?php echo __('phone'); ?></label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password"><?php echo __('password'); ?></label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_confirm"><?php echo __('password_confirm'); ?></label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
                        </div>
                        <p class="small text-muted"><?php echo __('cms_register_trial_note'); ?></p>
                        <button type="submit" class="btn btn-primary w-100"><?php echo __('cms_register_submit'); ?></button>
                    </form>
                    <hr class="my-4">
                    <p class="text-center mb-0">
                        <?php echo __('cms_have_account'); ?>
                        <a href="<?php echo rateb_url('site/login'); ?>"><?php echo __('login'); ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
