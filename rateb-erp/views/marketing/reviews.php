<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container"><div class="row g-4">
<?php foreach ($allTestimonials ?? $testimonials ?? [] as $t) { ?>
<div class="col-md-4"><blockquote class="rateb-mkt-testimonial">
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($t, 'quote')); ?></p>
<footer>
<strong><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($t, 'customer_name')); ?></strong>
<?php
$tPos = CmsService::pickLocale($t, 'position');
$tCo = CmsService::pickLocale($t, 'company');
$tMeta = $tPos !== '' && $tCo !== '' ? $tPos . ' — ' . $tCo : ($tPos !== '' ? $tPos : $tCo);
if ($tMeta !== '') { ?>
<span><?php echo Rateb\App\Core\View::escape($tMeta); ?></span>
<?php } ?>
</footer>
</blockquote></div>
<?php } ?>
</div></div></section>
