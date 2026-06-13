<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($article ?? [], 'title')); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container col-lg-8 mx-auto rateb-mkt-article-body">
<?php echo nl2br(Rateb\App\Core\View::escape(CmsService::pickLocale($article ?? [], 'content'))); ?>
</div></section>
