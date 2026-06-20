<?php
/** @var array<int, array<string, mixed>> $plans */
/** @var array<string, array<string, mixed>> $content */
use Rateb\App\Models\Plan;
use Rateb\App\Services\CmsService;

$intro = $content['intro']['section'] ?? null;
$planCount = count($plans ?? []);
$colClass = $planCount >= 3 ? 'col-lg-4' : ($planCount === 2 ? 'col-md-6' : 'col-md-8 mx-auto');
?>
<section class="rateb-mkt-page-hero">
    <div class="container text-center">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
        <?php if ($intro) { ?>
        <p class="rateb-mkt-page-hero-lead mb-0"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($intro, 'body')); ?></p>
        <?php } ?>
    </div>
</section>
<section class="rateb-mkt-section" id="programs">
    <div class="container">
        <?php if ($intro && trim(CmsService::pickLocale($intro, 'title')) !== '') { ?>
        <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($intro, 'title')); ?></h2>
        <?php } ?>
        <div class="rateb-mkt-pricing-toggle text-center mb-4" data-rateb-pricing-toggle role="group" aria-label="<?php echo __('cms_billing_cycle'); ?>">
            <button type="button" class="btn btn-sm btn-outline-primary" data-billing-cycle="monthly"><?php echo __('monthly'); ?></button>
            <button type="button" class="btn btn-sm btn-outline-primary active" data-billing-cycle="yearly"><?php echo __('yearly'); ?></button>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($plans ?? [] as $plan) {
                $slug = (string) ($plan['slug'] ?? '');
                $isFeatured = $slug === 'professional' || $slug === 'gold';
                ?>
            <div class="<?php echo $colClass; ?>">
                <div class="rateb-mkt-plan-card rateb-mkt-plan-card--full<?php echo $isFeatured ? ' rateb-mkt-plan-card--featured' : ''; ?>" data-plan-card>
                    <?php if ($isFeatured) { ?><span class="rateb-mkt-plan-badge"><?php echo __('cms_plan_popular'); ?></span><?php } ?>
                    <h3><?php echo Rateb\App\Core\View::escape(Plan::marketingName($plan)); ?></h3>
                    <p class="rateb-mkt-plan-desc"><?php echo Rateb\App\Core\View::escape(Plan::marketingDescription($plan)); ?></p>
                    <div class="rateb-mkt-plan-prices">
                        <p class="rateb-mkt-plan-price" data-price-monthly hidden>
                            <?php echo Rateb\App\Core\View::escape(Plan::marketingPrice($plan)); ?>
                            <small><?php echo __('sar'); ?> / <?php echo __('month'); ?></small>
                        </p>
                        <p class="rateb-mkt-plan-price" data-price-yearly>
                            <?php echo Rateb\App\Core\View::escape(Plan::marketingYearlyPrice($plan)); ?>
                            <small><?php echo __('sar'); ?> / <?php echo __('yearly'); ?></small>
                        </p>
                    </div>
                    <?php
                    $features = Plan::marketingFeatures($plan);
                    if ($features !== []) { ?>
                    <ul class="rateb-mkt-plan-features">
                        <?php foreach ($features as $feature) { ?>
                        <li><?php echo Rateb\App\Core\View::escape($feature); ?></li>
                        <?php } ?>
                    </ul>
                    <?php } ?>
                    <div class="rateb-mkt-plan-actions">
                        <a href="<?php echo rateb_url('site/register'); ?>" class="btn btn-primary"><?php echo __('cms_register'); ?></a>
                        <a href="<?php echo rateb_url('site/request-demo'); ?>" class="btn btn-outline-primary"><?php echo __('cms_request_demo'); ?></a>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
<script src="<?php echo rateb_asset('js/marketing-pricing.js'); ?>"></script>
