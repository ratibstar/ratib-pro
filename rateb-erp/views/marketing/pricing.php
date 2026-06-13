<?php
/** @var array<int, array<string, mixed>> $plans */
use Rateb\App\Models\Plan;
?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container"><div class="row g-4">
<?php foreach ($plans ?? [] as $plan) { ?>
<div class="col-md-4"><div class="rateb-mkt-plan-card">
<h3><?php echo Rateb\App\Core\View::escape(Plan::marketingName($plan)); ?></h3>
<p><?php echo Rateb\App\Core\View::escape(Plan::marketingDescription($plan)); ?></p>
<p class="rateb-mkt-plan-price"><?php echo Rateb\App\Core\View::escape(Plan::marketingPrice($plan)); ?> <?php echo __('sar'); ?> / <?php echo __('month'); ?></p>
<a href="<?php echo rateb_url('site/request-demo'); ?>" class="btn btn-primary"><?php echo __('cms_request_demo'); ?></a>
</div></div>
<?php } ?>
</div></div></section>
