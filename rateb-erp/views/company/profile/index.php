<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('login_badge'); ?></div>
    <div class="rateb-card-body">
        <?php if (!empty($loginBarcode)) { ?>
        <div class="row g-3 align-items-center">
            <div class="col-md-5 text-center">
                <?php if (!empty($badgeQrUrl)) { ?>
                <img src="<?php echo Rateb\App\Core\View::escape($badgeQrUrl); ?>" alt="<?php echo __('qr_code'); ?>" class="rateb-login-qr-img">
                <?php } ?>
            </div>
            <div class="col-md-7">
                <p class="text-muted small mb-2"><?php echo __('login_badge_hint'); ?></p>
                <div class="font-monospace fs-5 text-center p-2 border rounded mb-3"><?php echo Rateb\App\Core\View::escape($loginBarcode); ?></div>
                <form method="post" action="<?php echo rateb_app_url('profile/regenerate-barcode'); ?>" class="d-inline"
                    onsubmit="return confirm('<?php echo Rateb\App\Core\View::escape(__('barcode_regenerate_confirm')); ?>');">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <button type="submit" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-rotate"></i> <?php echo __('barcode_regenerate'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php } else { ?>
        <p class="text-muted mb-0"><?php echo __('barcode_unavailable'); ?></p>
        <?php } ?>
    </div>
</div>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('profile'); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_app_url('profile'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" value="<?php echo Rateb\App\Core\View::escape($user['name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input class="form-control" value="<?php echo Rateb\App\Core\View::escape($user['email'] ?? ''); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="phone" value="<?php echo Rateb\App\Core\View::escape($user['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('language'); ?></label>
                    <select class="form-select" name="locale">
                        <option value="en"<?php echo ($user['locale'] ?? '') === 'en' ? ' selected' : ''; ?>>English</option>
                        <option value="ar"<?php echo ($user['locale'] ?? '') === 'ar' ? ' selected' : ''; ?>>العربية</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">New Password</label>
                    <input class="form-control" type="password" name="password" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
        </form>
    </div>
</div>
