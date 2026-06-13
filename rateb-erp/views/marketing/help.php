<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container col-lg-8 mx-auto">
<?php foreach ($helpArticles ?? [] as $a) { ?>
<div class="rateb-mkt-help-item mb-4">
<h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($a, 'title')); ?></h3>
<p><?php echo nl2br(Rateb\App\Core\View::escape(CmsService::pickLocale($a, 'content'))); ?></p>
</div>
<?php } ?>
</div></section>
