<section class="rateb-portal-section">
    <div class="container rateb-portal-auth">
        <h1><?php echo Rateb\App\Core\View::escape(ucfirst((string) ($portalType ?? '')) . ' — ' . (__('register') ?: 'Register')); ?></h1>
        <form class="rateb-portal-form" method="post" action="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/register'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-portal-form__field"><span><?php echo __('full_name') ?: 'Full name'; ?> *</span><input type="text" name="full_name" required></label>
            <label class="rateb-portal-form__field"><span><?php echo __('organization') ?: 'Organization'; ?></span><input type="text" name="organization_name"></label>
            <label class="rateb-portal-form__field"><span><?php echo __('email') ?: 'Email'; ?> *</span><input type="email" name="email" required></label>
            <label class="rateb-portal-form__field"><span><?php echo __('phone') ?: 'Phone'; ?></span><input type="tel" name="phone"></label>
            <label class="rateb-portal-form__field"><span><?php echo __('password') ?: 'Password'; ?> *</span><input type="password" name="password" required minlength="8"></label>
            <button type="submit" class="rateb-portal-btn"><?php echo __('register') ?: 'Register'; ?></button>
        </form>
        <p class="rateb-portal-auth__alt"><a href="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/login'); ?>"><?php echo __('already_have_account') ?: 'Already have an account?'; ?></a></p>
    </div>
</section>
