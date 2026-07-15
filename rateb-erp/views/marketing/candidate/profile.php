<section class="rateb-career-section">
    <div class="container rateb-career-portal">
        <h1><?php echo __('profile') ?: 'Profile'; ?></h1>
        <form class="rateb-career-form" method="post" action="<?php echo rateb_url('site/candidate/profile'); ?>" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-career-form__field"><span><?php echo __('full_name') ?: 'Full name'; ?></span><input type="text" name="full_name" value="<?php echo Rateb\App\Core\View::escape((string) ($portalUser['full_name'] ?? '')); ?>"></label>
            <label class="rateb-career-form__field"><span><?php echo __('phone') ?: 'Phone'; ?></span><input type="tel" name="phone" value="<?php echo Rateb\App\Core\View::escape((string) ($portalUser['phone'] ?? '')); ?>"></label>
            <label class="rateb-career-form__field"><span><?php echo __('city') ?: 'City'; ?></span><input type="text" name="city" value="<?php echo Rateb\App\Core\View::escape((string) ($portalUser['city'] ?? '')); ?>"></label>
            <label class="rateb-career-form__field"><span><?php echo __('linkedin') ?: 'LinkedIn'; ?></span><input type="url" name="linkedin_url" value="<?php echo Rateb\App\Core\View::escape((string) ($portalUser['linkedin_url'] ?? '')); ?>"></label>
            <label class="rateb-career-form__field"><span><?php echo __('portfolio') ?: 'Portfolio'; ?></span><input type="url" name="portfolio_url" value="<?php echo Rateb\App\Core\View::escape((string) ($portalUser['portfolio_url'] ?? '')); ?>"></label>
            <label class="rateb-career-form__field"><span><?php echo __('new_password') ?: 'New password'; ?></span><input type="password" name="password" minlength="8"></label>
            <label class="rateb-career-form__field rateb-career-form__field--full"><span><?php echo __('resume') ?: 'CV (PDF)'; ?></span><input type="file" name="resume" accept=".pdf,application/pdf"></label>
            <?php if (!empty($portalUser['resume_path'])) { ?>
            <p class="rateb-career-cv-hint"><?php echo __('cv_on_file') ?: 'CV on file'; ?></p>
            <?php } ?>
            <button type="submit" class="rateb-career-btn rateb-career-btn--primary"><?php echo __('save') ?: 'Save'; ?></button>
        </form>
    </div>
</section>
