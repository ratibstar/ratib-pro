<?php
use Rateb\App\Services\CmsService;
use Rateb\App\Website\Career\CareerJobService;
$job = $job ?? [];
$slug = (string) ($job['slug'] ?? '');
?>
<section class="rateb-career-section">
    <div class="container rateb-career-detail">
        <a class="rateb-career-back" href="<?php echo rateb_url('site/careers'); ?>"><?php echo __('back_to_careers') ?: '← All careers'; ?></a>
        <header class="rateb-career-detail__header">
            <h1><?php echo Rateb\App\Core\View::escape(CareerJobService::jobTitle($job)); ?></h1>
            <p class="rateb-career-detail__meta">
                <?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($job, 'department')); ?>
                — <?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($job, 'location')); ?>
            </p>
            <div class="rateb-career-detail__actions">
                <a class="rateb-career-btn rateb-career-btn--primary" href="<?php echo rateb_url('site/careers/job/' . rawurlencode($slug) . '/apply'); ?>"><?php echo __('apply_online') ?: 'Apply Online'; ?></a>
                <?php if (!empty($portalUser)) { ?>
                <form method="post" action="<?php echo rateb_url('site/candidate/save/' . (int) ($job['id'] ?? 0)); ?>" class="rateb-career-inline-form">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                    <button type="submit" class="rateb-career-btn rateb-career-btn--ghost"><?php echo __('save_job') ?: 'Save'; ?></button>
                </form>
                <?php } ?>
            </div>
        </header>
        <div class="rateb-career-detail__body">
            <div class="rateb-career-detail__desc"><?php echo nl2br(Rateb\App\Core\View::escape(CmsService::pickLocale($job, 'description'))); ?></div>
            <?php $req = CmsService::pickLocale($job, 'requirements'); if ($req !== '') { ?>
            <h2><?php echo __('requirements') ?: 'Requirements'; ?></h2>
            <div><?php echo nl2br(Rateb\App\Core\View::escape($req)); ?></div>
            <?php } ?>
            <?php $ben = CmsService::pickLocale($job, 'benefits'); if ($ben !== '') { ?>
            <h2><?php echo __('benefits') ?: 'Benefits'; ?></h2>
            <div><?php echo nl2br(Rateb\App\Core\View::escape($ben)); ?></div>
            <?php } ?>
        </div>
        <?php if (!empty($relatedJobs)) { ?>
        <aside class="rateb-career-related">
            <h2><?php echo __('related_jobs') ?: 'Related Jobs'; ?></h2>
            <div class="rateb-career-grid rateb-career-grid--compact">
                <?php foreach ($relatedJobs as $rj) { $jobCard = $rj; require RATEB_ROOT . '/views/marketing/careers/partials/job-card.php'; } ?>
            </div>
        </aside>
        <?php } ?>
    </div>
</section>
