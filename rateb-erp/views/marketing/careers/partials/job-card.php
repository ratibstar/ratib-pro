<?php
use Rateb\App\Services\CmsService;
use Rateb\App\Website\Career\CareerJobService;
$job = $jobCard ?? [];
if ($job === []) return;
$url = CareerJobService::jobUrl($job);
?>
<article class="rateb-career-card">
    <h3 class="rateb-career-card__title"><a href="<?php echo Rateb\App\Core\View::escape($url); ?>"><?php echo Rateb\App\Core\View::escape(CareerJobService::jobTitle($job)); ?></a></h3>
    <p class="rateb-career-card__meta">
        <?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($job, 'department')); ?>
        <?php if (CmsService::pickLocale($job, 'location') !== '') { ?>
        — <?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($job, 'location')); ?>
        <?php } ?>
    </p>
    <?php if (!empty($job['featured'])) { ?><span class="rateb-career-card__badge"><?php echo __('featured') ?: 'Featured'; ?></span><?php } ?>
    <a class="rateb-career-card__cta" href="<?php echo Rateb\App\Core\View::escape($url); ?>"><?php echo __('view_job') ?: 'View'; ?></a>
</article>
