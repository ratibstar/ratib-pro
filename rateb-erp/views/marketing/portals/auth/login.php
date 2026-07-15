<section class="rateb-portal-section">
    <div class="container rateb-portal-auth">
        <h1><?php echo Rateb\App\Core\View::escape(ucfirst((string) ($portalType ?? '')) . ' — ' . (__('login') ?: 'Login')); ?></h1>
        <form class="rateb-portal-form" method="post" action="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/login'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-portal-form__field"><span><?php echo __('email') ?: 'Email'; ?></span><input type="email" name="email" required></label>
            <label class="rateb-portal-form__field"><span><?php echo __('password') ?: 'Password'; ?></span><input type="password" name="password" required></label>
            <button type="submit" class="rateb-portal-btn"><?php echo __('login') ?: 'Login'; ?></button>
        </form>
        <p class="rateb-portal-auth__alt"><a href="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/register'); ?>"><?php echo __('create_account') ?: 'Create account'; ?></a></p>
    </div>
</section>
