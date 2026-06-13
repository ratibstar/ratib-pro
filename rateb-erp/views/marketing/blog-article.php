<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($article ?? [], 'title')); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container col-lg-8 mx-auto rateb-mkt-article-body">
<?php if (!empty($articleTags)) { ?>
<div class="rateb-mkt-tags mb-3">
    <?php foreach ($articleTags as $tag) { ?>
    <span class="badge bg-primary"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($tag, 'name')); ?></span>
    <?php } ?>
</div>
<?php } ?>
<div class="rateb-mkt-article-content"><?php echo CmsService::sanitizeHtml(CmsService::pickLocale($article ?? [], 'content')); ?></div>
</div></section>
