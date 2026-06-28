<?php
/** @var array<string, array<string, mixed>> $content */
/** @var array<int, array<string, mixed>> $plans */
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
$sectionTitle = '';
$sectionLead = '';
$compact = false;
if (!empty($plans)) {
    require RATEB_ROOT . '/views/marketing/partials/plans-grid.php';
} else { ?>
<section class="rateb-mkt-section">
    <div class="container text-center">
        <p class="rateb-mkt-section-lead mb-4"><?php echo __('cms_pricing_unavailable'); ?></p>
        <a href="<?php echo rateb_url('site/contact'); ?>" class="btn btn-primary"><?php echo __('cms_contact_us'); ?></a>
    </div>
</section>
<?php } ?>
<section class="rateb-mkt-cta">
    <div class="container text-center">
        <h2 class="rateb-mkt-section-title text-white mb-3"><?php echo __('cms_pricing_cta_title'); ?></h2>
        <p class="mb-4 opacity-90"><?php echo __('cms_pricing_cta_body'); ?></p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="<?php echo rateb_url('site/request-demo'); ?>" class="btn btn-light btn-lg"><?php echo __('cms_request_demo'); ?></a>
            <a href="<?php echo rateb_url('site/contact'); ?>" class="btn btn-outline-light btn-lg"><?php echo __('cms_contact_us'); ?></a>
        </div>
    </div>
</section>
