<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container col-lg-8 mx-auto">
<?php foreach ($allCareers ?? [] as $job) { if (($job['status'] ?? '') !== 'open') continue; ?>
<div class="rateb-mkt-job-card mb-4">
<h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($job, 'title')); ?></h3>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($job, 'department')); ?> — <?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($job, 'location')); ?></p>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($job, 'description')); ?></p>
</div>
<?php } ?>
</div></section>
