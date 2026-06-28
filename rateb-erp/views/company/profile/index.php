<?php if (!empty($loginBarcode)) {
    Rateb\App\Core\View::partial('login-badge-card', [
        'loginBarcode' => $loginBarcode,
        'badgeScanQrUrl' => $badgeScanQrUrl ?? '',
        'badgeLoginUrl' => $badgeLoginUrl ?? '',
        'csrf' => $csrf,
        'regenerateAction' => $badgeRegenerateAction ?? rateb_app_url('profile/regenerate-barcode'),
    ]);
} else { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('login_badge'); ?></div>
    <div class="rateb-card-body">
        <p class="text-muted mb-0"><?php echo __('barcode_unavailable'); ?></p>
    </div>
</div>
<?php } ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('profile'); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_app_url('profile'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('name'); ?></label>
                    <input class="form-control" name="name" value="<?php echo Rateb\App\Core\View::escape($user['name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('email'); ?></label>
                    <input class="form-control" value="<?php echo Rateb\App\Core\View::escape($user['email'] ?? ''); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('phone'); ?></label>
                    <input class="form-control" name="phone" value="<?php echo Rateb\App\Core\View::escape($user['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('language'); ?></label>
                    <select class="form-select" name="locale">
                        <option value="en"<?php echo ($user['locale'] ?? '') === 'en' ? ' selected' : ''; ?>><?php echo __('lang_en'); ?></option>
                        <option value="ar"<?php echo ($user['locale'] ?? '') === 'ar' ? ' selected' : ''; ?>><?php echo __('lang_ar'); ?></option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('new_password'); ?></label>
                    <input class="form-control" type="password" name="password" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
        </form>
    </div>
</div>
