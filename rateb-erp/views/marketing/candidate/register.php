<section class="rateb-career-section">
    <div class="container rateb-career-auth">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
        <form class="rateb-career-form" method="post" action="<?php echo rateb_url('site/candidate/register'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-career-form__field"><span><?php echo __('full_name') ?: 'Full name'; ?> *</span><input type="text" name="full_name" required></label>
            <label class="rateb-career-form__field"><span><?php echo __('email') ?: 'Email'; ?> *</span><input type="email" name="email" required></label>
            <label class="rateb-career-form__field"><span><?php echo __('phone') ?: 'Phone'; ?></span><input type="tel" name="phone"></label>
            <label class="rateb-career-form__field"><span><?php echo __('password') ?: 'Password'; ?> *</span><input type="password" name="password" required minlength="8"></label>
            <button type="submit" class="rateb-career-btn rateb-career-btn--primary"><?php echo __('register') ?: 'Register'; ?></button>
        </form>
        <p class="rateb-career-auth__alt"><a href="<?php echo rateb_url('site/candidate/login'); ?>"><?php echo __('already_have_account') ?: 'Already have an account?'; ?></a></p>
    </div>
</section>
