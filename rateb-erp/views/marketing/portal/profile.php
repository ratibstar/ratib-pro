<?php /** @var array<string, mixed>|null $user */ /** @var string $csrf */ ?>
<div class="rateb-portal-page">
    <div class="container py-4">
        <div class="mb-4">
            <a href="<?php echo rateb_url('site/portal'); ?>" class="text-decoration-none small"><i class="fas fa-arrow-right ms-1"></i><?php echo __('portal_back'); ?></a>
            <h1 class="h3 mt-2"><?php echo __('profile'); ?></h1>
            <p class="text-muted mb-0"><?php echo __('portal_profile_hint'); ?></p>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <form method="post" action="<?php echo rateb_url('site/portal/profile'); ?>" class="rateb-mkt-form">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <div class="mb-3">
                                <label class="form-label" for="name"><?php echo __('name'); ?></label>
                                <input type="text" class="form-control" id="name" name="name" required value="<?php echo Rateb\App\Core\View::escape((string) ($user['name'] ?? '')); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('login_email'); ?></label>
                                <input type="email" class="form-control" value="<?php echo Rateb\App\Core\View::escape((string) ($user['email'] ?? '')); ?>" disabled>
                                <div class="form-text"><?php echo __('portal_email_locked'); ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="phone"><?php echo __('phone'); ?></label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo Rateb\App\Core\View::escape((string) ($user['phone'] ?? '')); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="locale"><?php echo __('language'); ?></label>
                                <select class="form-select" id="locale" name="locale">
                                    <option value="ar"<?php echo rateb_locale() === 'ar' ? ' selected' : ''; ?>>عربي</option>
                                    <option value="en"<?php echo rateb_locale() === 'en' ? ' selected' : ''; ?>>English</option>
                                </select>
                            </div>
                            <hr>
                            <p class="small text-muted"><?php echo __('portal_password_optional'); ?></p>
                            <div class="mb-3">
                                <label class="form-label" for="password"><?php echo __('password'); ?></label>
                                <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password_confirm"><?php echo __('password_confirm'); ?></label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="8" autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
