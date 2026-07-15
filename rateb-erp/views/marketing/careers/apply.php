<?php
use Rateb\App\Services\CmsService;
use Rateb\App\Website\Career\CareerJobService;
$job = $job ?? [];
$slug = (string) ($job['slug'] ?? '');
$pu = $portalUser ?? null;
?>
<section class="rateb-career-section">
    <div class="container rateb-career-apply">
        <h1><?php echo __('apply_online') ?: 'Apply Online'; ?></h1>
        <p class="rateb-career-apply__job"><?php echo Rateb\App\Core\View::escape(CareerJobService::jobTitle($job)); ?></p>
        <form class="rateb-career-form" method="post" action="<?php echo rateb_url('site/careers/job/' . rawurlencode($slug) . '/apply'); ?>" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <div class="rateb-career-form__grid">
                <label class="rateb-career-form__field"><span><?php echo __('full_name') ?: 'Full name'; ?> *</span><input type="text" name="full_name" required value="<?php echo Rateb\App\Core\View::escape((string) ($pu['full_name'] ?? '')); ?>"></label>
                <label class="rateb-career-form__field"><span><?php echo __('email') ?: 'Email'; ?> *</span><input type="email" name="email" required value="<?php echo Rateb\App\Core\View::escape((string) ($pu['email'] ?? '')); ?>"></label>
                <label class="rateb-career-form__field"><span><?php echo __('phone') ?: 'Phone'; ?></span><input type="tel" name="phone" value="<?php echo Rateb\App\Core\View::escape((string) ($pu['phone'] ?? '')); ?>"></label>
                <label class="rateb-career-form__field"><span><?php echo __('nationality') ?: 'Nationality'; ?></span><input type="text" name="nationality" maxlength="2" placeholder="SA"></label>
                <label class="rateb-career-form__field"><span><?php echo __('country') ?: 'Country'; ?></span><input type="text" name="country" maxlength="2"></label>
                <label class="rateb-career-form__field"><span><?php echo __('city') ?: 'City'; ?></span><input type="text" name="city" value="<?php echo Rateb\App\Core\View::escape((string) ($pu['city'] ?? '')); ?>"></label>
                <label class="rateb-career-form__field"><span><?php echo __('expected_salary') ?: 'Expected salary'; ?></span><input type="number" name="expected_salary" min="0" step="100"></label>
                <label class="rateb-career-form__field"><span><?php echo __('availability') ?: 'Availability'; ?></span><input type="date" name="availability_date"></label>
                <label class="rateb-career-form__field"><span><?php echo __('linkedin') ?: 'LinkedIn'; ?></span><input type="url" name="linkedin" value="<?php echo Rateb\App\Core\View::escape((string) ($pu['linkedin_url'] ?? '')); ?>"></label>
                <label class="rateb-career-form__field"><span><?php echo __('portfolio') ?: 'Portfolio'; ?></span><input type="url" name="portfolio" value="<?php echo Rateb\App\Core\View::escape((string) ($pu['portfolio_url'] ?? '')); ?>"></label>
            </div>
            <label class="rateb-career-form__field rateb-career-form__field--full"><span><?php echo __('experience') ?: 'Experience'; ?></span><textarea name="experience" rows="3"></textarea></label>
            <label class="rateb-career-form__field rateb-career-form__field--full"><span><?php echo __('education') ?: 'Education'; ?></span><textarea name="education" rows="2"></textarea></label>
            <label class="rateb-career-form__field rateb-career-form__field--full"><span><?php echo __('skills') ?: 'Skills'; ?></span><input type="text" name="skills" placeholder="<?php echo __('skills_placeholder') ?: 'PHP, SQL, …'; ?>"></label>
            <label class="rateb-career-form__field rateb-career-form__field--full"><span><?php echo __('languages') ?: 'Languages'; ?></span><input type="text" name="languages"></label>
            <label class="rateb-career-form__field rateb-career-form__field--full"><span><?php echo __('cover_letter') ?: 'Cover letter'; ?></span><textarea name="cover_letter" rows="5"></textarea></label>
            <label class="rateb-career-form__field rateb-career-form__field--full"><span><?php echo __('resume') ?: 'Resume (PDF)'; ?></span><input type="file" name="resume" accept=".pdf,application/pdf"></label>
            <button type="submit" class="rateb-career-btn rateb-career-btn--primary"><?php echo __('submit_application') ?: 'Submit application'; ?></button>
        </form>
    </div>
</section>
