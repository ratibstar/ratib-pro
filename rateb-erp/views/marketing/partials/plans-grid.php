<?php
/** @var array<int, array<string, mixed>> $plans */
/** @var bool $compact */
use Rateb\App\Models\Plan;

$compact = !empty($compact);
$plans = $plans ?? [];
if ($plans === []) {
    return;
}
$featuredSlug = 'professional';
?>
<section class="rateb-mkt-section<?php echo $compact ? ' rateb-mkt-section-alt' : ''; ?>" id="pricing">
    <div class="container">
        <?php if (!empty($sectionTitle)) { ?>
        <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape((string) $sectionTitle); ?></h2>
        <?php } ?>
        <?php if (!empty($sectionLead)) { ?>
        <p class="rateb-mkt-section-lead text-center mb-4"><?php echo Rateb\App\Core\View::escape((string) $sectionLead); ?></p>
        <?php } ?>
        <div class="row g-3 justify-content-center">
            <?php foreach ($plans as $plan) {
                $slug = trim((string) ($plan['slug'] ?? ''));
                $isFeatured = $slug === $featuredSlug;
                $features = Plan::marketingFeatures($plan);
                if ($compact && count($features) > 4) {
                    $features = array_slice($features, 0, 4);
                }
                ?>
            <div class="col-md-6 col-lg-4">
                <article class="rateb-mkt-plan-card rateb-mkt-plan-card--full<?php echo $isFeatured ? ' rateb-mkt-plan-card--featured' : ''; ?>">
                    <?php if ($isFeatured) { ?>
                    <span class="rateb-mkt-plan-badge"><?php echo __('cms_plan_popular'); ?></span>
                    <?php } ?>
                    <h3 class="h5 mb-1"><?php echo Rateb\App\Core\View::escape(Plan::marketingName($plan)); ?></h3>
                    <p class="rateb-mkt-plan-desc mb-2"><?php echo Rateb\App\Core\View::escape(Plan::marketingDescription($plan)); ?></p>
                    <p class="rateb-mkt-plan-price mb-0">
                        <?php echo Rateb\App\Core\View::escape(Plan::marketingPrice($plan)); ?>
                        <small class="text-muted"><?php echo __('sar'); ?> / <?php echo __('cms_per_month'); ?></small>
                    </p>
                    <?php if (!$compact && (float) ($plan['price_yearly'] ?? 0) > 0) { ?>
                    <p class="small text-muted mb-0">
                        <?php echo Rateb\App\Core\View::escape(Plan::marketingYearlyPrice($plan)); ?>
                        <?php echo __('sar'); ?> / <?php echo __('cms_per_year'); ?>
                    </p>
                    <?php } ?>
                    <?php if ($features !== []) { ?>
                    <ul class="rateb-mkt-plan-features">
                        <?php foreach ($features as $feature) { ?>
                        <li><?php echo Rateb\App\Core\View::escape($feature); ?></li>
                        <?php } ?>
                    </ul>
                    <?php } ?>
                    <div class="rateb-mkt-plan-actions">
                        <a href="<?php echo Rateb\App\Core\View::escape(rateb_marketing_register_url($slug)); ?>" class="btn btn-sm btn-primary w-100"><?php echo __('cms_register'); ?></a>
                        <?php if ($compact) { ?>
                        <a href="<?php echo Rateb\App\Core\View::escape(rateb_url('site/request-demo')); ?>" class="btn btn-sm btn-outline-primary w-100"><?php echo __('cms_request_demo'); ?></a>
                        <?php } ?>
                    </div>
                </article>
            </div>
            <?php } ?>
        </div>
        <?php if ($compact) { ?>
        <div class="text-center mt-4">
            <a href="<?php echo rateb_url('site/pricing'); ?>" class="btn btn-outline-primary rateb-mkt-more-btn">
                <i class="fas fa-circle-plus ms-1"></i><?php echo __('cms_view_all_pricing'); ?>
            </a>
        </div>
        <?php } ?>
    </div>
</section>
