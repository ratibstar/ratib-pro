<?php
use Rateb\App\Services\CmsService;
$about = $about ?? null;
?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<?php if ($about) { ?>
<section class="rateb-mkt-section"><div class="container col-lg-8 mx-auto">
<h2><?php echo __('cms_our_story'); ?></h2>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($about, 'story')); ?></p>
<h2><?php echo __('cms_vision'); ?></h2>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($about, 'vision')); ?></p>
<h2><?php echo __('cms_mission'); ?></h2>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($about, 'mission')); ?></p>
</div></section>
<?php } ?>
