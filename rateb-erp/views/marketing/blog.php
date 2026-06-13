<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container"><div class="row g-4">
<?php foreach ($allArticles ?? $articles ?? [] as $article) { ?>
<div class="col-md-6 col-lg-4"><article class="rateb-mkt-article-card">
<h3><a href="<?php echo rateb_url('site/blog/' . ($article['slug'] ?? '')); ?>"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($article, 'title')); ?></a></h3>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($article, 'excerpt')); ?></p>
</article></div>
<?php } ?>
</div></div></section>
