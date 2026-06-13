<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container"><div class="row g-4">
<?php foreach ($allServices ?? [] as $svc) { ?>
<div class="col-md-6"><div class="rateb-mkt-feature-card">
<?php if (!empty($svc['icon'])) { ?><i class="fas <?php echo Rateb\App\Core\View::escape($svc['icon']); ?>"></i><?php } ?>
<h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($svc, 'title')); ?></h3>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($svc, 'summary')); ?></p>
</div></div>
<?php } ?>
</div></div></section>
