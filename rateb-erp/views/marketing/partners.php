<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container"><div class="row g-4 justify-content-center">
<?php foreach ($allPartners ?? [] as $p) { ?>
<div class="col-6 col-md-3 text-center"><div class="rateb-mkt-partner-card"><span><?php echo Rateb\App\Core\View::escape(\Rateb\App\Services\CmsService::pickLocale($p, 'name')); ?></span></div></div>
<?php } ?>
</div></div></section>
