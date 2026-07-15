<?php
use Rateb\App\Services\CmsService;
use Rateb\App\Website\Career\CareerJobService;
?>
<?php require RATEB_ROOT . '/views/marketing/careers/partials/job-card.php'; ?>
<section class="rateb-career-hero">
    <div class="container">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
        <p class="rateb-career-hero__lead"><?php echo __('careers_home_lead') ?: 'Join our team — explore open positions.'; ?></p>
        <form class="rateb-career-search rateb-career-search--hero" method="get" action="<?php echo rateb_url('site/careers/search'); ?>">
            <input type="search" name="q" placeholder="<?php echo __('job_search_placeholder') ?: 'Search jobs…'; ?>" class="rateb-career-search__input">
            <button type="submit" class="rateb-career-search__btn"><?php echo __('search') ?: 'Search'; ?></button>
        </form>
    </div>
</section>
<?php if (!empty($featuredJobs)) { ?>
<section class="rateb-career-section">
    <div class="container">
        <h2><?php echo __('featured_jobs') ?: 'Featured Jobs'; ?></h2>
        <div class="rateb-career-grid">
            <?php foreach ($featuredJobs as $job) { $jobCard = $job; require RATEB_ROOT . '/views/marketing/careers/partials/job-card.php'; } ?>
        </div>
    </div>
</section>
<?php } ?>
<?php if (!empty($categories)) { ?>
<section class="rateb-career-section rateb-career-section--muted">
    <div class="container">
        <h2><?php echo __('job_categories') ?: 'Categories'; ?></h2>
        <ul class="rateb-career-categories">
            <?php foreach ($categories as $cat) {
                $slug = (string) ($cat['slug'] ?? '');
                if ($slug === '') continue;
            ?>
            <li><a href="<?php echo rateb_url('site/careers/category/' . rawurlencode($slug)); ?>"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($cat, 'label')); ?> <span>(<?php echo (int) ($cat['job_count'] ?? 0); ?>)</span></a></li>
            <?php } ?>
        </ul>
    </div>
</section>
<?php } ?>
<section class="rateb-career-section">
    <div class="container">
        <h2><?php echo __('latest_jobs') ?: 'Latest Jobs'; ?></h2>
        <div class="rateb-career-grid" data-career-lazy-grid>
            <?php foreach ($latestJobs ?? [] as $job) { $jobCard = $job; require RATEB_ROOT . '/views/marketing/careers/partials/job-card.php'; } ?>
        </div>
    </div>
</section>
