<?php
use Rateb\App\Services\CmsService;
$blocks = $content['list']['blocks'] ?? $content['industries']['blocks'] ?? [];
?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container"><div class="row g-4">
<?php foreach ($blocks as $block) { ?>
<div class="col-md-6 col-lg-4"><div class="rateb-mkt-icon-card">
<?php if (!empty($block['icon'])) { ?><i class="fas <?php echo Rateb\App\Core\View::escape($block['icon']); ?>"></i><?php } ?>
<h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'title')); ?></h3>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'content')); ?></p>
</div></div>
<?php } ?>
</div></div></section>
