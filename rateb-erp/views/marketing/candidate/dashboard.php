<?php use Rateb\App\Services\CmsService; use Rateb\App\Website\Career\CareerJobService; ?>
<section class="rateb-career-section">
    <div class="container rateb-career-portal">
        <h1><?php echo __('portal_dashboard') ?: 'Candidate Portal'; ?></h1>
        <p><?php echo __('welcome') ?: 'Welcome'; ?>, <?php echo Rateb\App\Core\View::escape((string) ($portalUser['full_name'] ?? '')); ?></p>
        <div class="rateb-career-portal__links">
            <a href="<?php echo rateb_url('site/candidate/applications'); ?>"><?php echo __('applications') ?: 'Applications'; ?></a>
            <a href="<?php echo rateb_url('site/candidate/saved'); ?>"><?php echo __('saved_jobs') ?: 'Saved jobs'; ?></a>
            <a href="<?php echo rateb_url('site/candidate/profile'); ?>"><?php echo __('profile') ?: 'Profile'; ?></a>
        </div>
        <h2><?php echo __('recent_applications') ?: 'Recent applications'; ?></h2>
        <ul class="rateb-career-list">
            <?php foreach (array_slice($applications ?? [], 0, 5) as $app) { ?>
            <li>
                <strong><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($app, 'title')); ?></strong>
                — <span class="rateb-career-status rateb-career-status--<?php echo Rateb\App\Core\View::escape((string) ($app['status'] ?? '')); ?>"><?php echo Rateb\App\Core\View::escape((string) ($app['status'] ?? '')); ?></span>
            </li>
            <?php } ?>
        </ul>
    </div>
</section>
