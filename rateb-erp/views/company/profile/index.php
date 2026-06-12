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
