<?php
/** @var array<string, array<string, mixed>> $content */
use Rateb\App\Services\CmsService;

$intro = $content['intro']['section'] ?? null;
?>
<section class="rateb-mkt-page-hero">
    <div class="container text-center">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
        <?php if ($intro) { ?>
        <p class="rateb-mkt-page-hero-lead mb-0"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($intro, 'body')); ?></p>
        <?php } ?>
    </div>
</section>
<?php
\Rateb\App\Services\LegacyHomeContentService::render('commerce');
