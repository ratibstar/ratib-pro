<section class="rateb-career-section">
    <div class="container rateb-career-auth">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
        <form class="rateb-career-form" method="post" action="<?php echo rateb_url('site/candidate/login'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-career-form__field"><span><?php echo __('email') ?: 'Email'; ?></span><input type="email" name="email" required></label>
            <label class="rateb-career-form__field"><span><?php echo __('password') ?: 'Password'; ?></span><input type="password" name="password" required></label>
            <button type="submit" class="rateb-career-btn rateb-career-btn--primary"><?php echo __('login') ?: 'Login'; ?></button>
        </form>
        <p class="rateb-career-auth__alt"><a href="<?php echo rateb_url('site/candidate/register'); ?>"><?php echo __('create_account') ?: 'Create account'; ?></a></p>
    </div>
</section>
