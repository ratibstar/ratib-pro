<section class="rateb-portal-section">
    <div class="container rateb-portal-auth">
        <h1><?php echo __('profile') ?: 'Profile'; ?></h1>
        <form class="rateb-portal-form" method="post" action="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/profile'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-portal-form__field"><span><?php echo __('full_name') ?: 'Full name'; ?></span><input type="text" name="full_name" value="<?php echo Rateb\App\Core\View::escape((string) ($user['full_name'] ?? '')); ?>"></label>
            <label class="rateb-portal-form__field"><span><?php echo __('organization') ?: 'Organization'; ?></span><input type="text" name="organization_name" value="<?php echo Rateb\App\Core\View::escape((string) ($user['organization_name'] ?? '')); ?>"></label>
            <label class="rateb-portal-form__field"><span><?php echo __('phone') ?: 'Phone'; ?></span><input type="tel" name="phone" value="<?php echo Rateb\App\Core\View::escape((string) ($user['phone'] ?? '')); ?>"></label>
            <label class="rateb-portal-form__field"><span><?php echo __('new_password') ?: 'New password'; ?></span><input type="password" name="password" minlength="8"></label>
            <button type="submit" class="rateb-portal-btn"><?php echo __('save') ?: 'Save'; ?></button>
        </form>
    </div>
</section>
