<?php
/** @var array<string, array<string, mixed>> $content */
use Rateb\App\Services\CmsService;
$hero = $content['hero']['section'] ?? null;
$slides = $slides ?? [];
?>
<section class="rateb-mkt-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <?php if ($hero) { ?>
                <h1 class="rateb-mkt-hero-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($hero, 'title')); ?></h1>
                <p class="rateb-mkt-hero-subtitle"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($hero, 'body')); ?></p>
                <?php } elseif (!empty($slides[0])) {
                    $s = $slides[0]; ?>
                <h1 class="rateb-mkt-hero-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'title')); ?></h1>
                <p class="rateb-mkt-hero-subtitle"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'subtitle')); ?></p>
                <?php } ?>
                <div class="rateb-mkt-hero-cta">
                    <a href="<?php echo rateb_url('site/request-demo'); ?>" class="btn btn-primary btn-lg"><?php echo __('cms_request_demo'); ?></a>
                    <a href="<?php echo rateb_url('site/features'); ?>" class="btn btn-outline-primary btn-lg"><?php echo __('cms_explore_features'); ?></a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="rateb-mkt-hero-card">
                    <i class="fas fa-chart-line"></i>
                    <span><?php
                    $heroCardText = __('cms_erp_overview_short');
                    if ($hero) {
                        $heroSettings = $hero['settings_json'] ?? null;
                        if (is_string($heroSettings) && $heroSettings !== '') {
                            $heroSettings = json_decode($heroSettings, true);
                        }
                        if (is_array($heroSettings)) {
                            $cardKey = rateb_locale() === 'ar' ? 'hero_card_ar' : 'hero_card_en';
                            $fromCms = trim((string) ($heroSettings[$cardKey] ?? ''));
                            if ($fromCms !== '') {
                                $heroCardText = $fromCms;
                            }
                        }
                    }
                    echo Rateb\App\Core\View::escape($heroCardText);
                    ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($content['erp_overview']['section'])) {
    $s = $content['erp_overview']['section']; ?>
<section class="rateb-mkt-section">
    <div class="container">
        <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'title')); ?></h2>
        <p class="rateb-mkt-section-lead"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'body')); ?></p>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['why_rateb']['section'])) {
    $s = $content['why_rateb']['section']; ?>
<section class="rateb-mkt-section rateb-mkt-section-alt">
    <div class="container">
        <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'title')); ?></h2>
        <p class="rateb-mkt-section-lead"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'body')); ?></p>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['trust']['blocks'])) {
    $s = $content['trust']['section'] ?? []; ?>
<section class="rateb-mkt-section rateb-mkt-trust">
    <div class="container">
        <?php if (!empty($s)) { ?>
        <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'title')); ?></h2>
        <?php if (trim(CmsService::pickLocale($s, 'body')) !== '') { ?>
        <p class="rateb-mkt-section-lead text-center mb-4"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'body')); ?></p>
        <?php } ?>
        <?php } ?>
        <div class="row g-3">
            <?php foreach ($content['trust']['blocks'] as $block) { ?>
            <div class="col-md-6 col-lg-3">
                <div class="rateb-mkt-trust-card">
                    <?php if (!empty($block['icon'])) { ?><i class="fas <?php echo Rateb\App\Core\View::escape($block['icon']); ?>"></i><?php } ?>
                    <h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'title')); ?></h3>
                    <p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'content')); ?></p>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['stats']['blocks'])) { ?>
<section class="rateb-mkt-stats">
    <div class="container">
        <div class="row g-3">
            <?php foreach ($content['stats']['blocks'] as $block) { ?>
            <div class="col-6 col-md-3">
                <div class="rateb-mkt-stat-card">
                    <div class="rateb-mkt-stat-value"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'content')); ?></div>
                    <div class="rateb-mkt-stat-label"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'title')); ?></div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['industries']['blocks'])) { ?>
<section class="rateb-mkt-section">
    <div class="container">
        <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($content['industries']['section'] ?? [], 'title')); ?></h2>
        <div class="row g-3">
            <?php foreach ($content['industries']['blocks'] as $block) { ?>
            <div class="col-md-4 col-lg">
                <div class="rateb-mkt-icon-card">
                    <?php if (!empty($block['icon'])) { ?><i class="fas <?php echo Rateb\App\Core\View::escape($block['icon']); ?>"></i><?php } ?>
                    <h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'title')); ?></h3>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($plans)) {
    $pricingSection = $content['pricing_preview']['section'] ?? [];
    $sectionTitle = CmsService::pickLocale($pricingSection, 'title') ?: __('cms_pricing_preview');
    $sectionLead = CmsService::pickLocale($pricingSection, 'body');
    $compact = true;
    require RATEB_ROOT . '/views/marketing/partials/plans-grid.php';
} ?>

<?php if (!empty($testimonials)) { ?>
<section class="rateb-mkt-section rateb-mkt-section-alt">
    <div class="container">
        <?php
        $sectionTitle = CmsService::pickLocale($content['testimonials']['section'] ?? [], 'title') ?: __('cms_testimonials');
        $moreUrl = rateb_url('site/reviews');
        require RATEB_ROOT . '/views/marketing/partials/section-head-more.php';
        ?>
        <div class="row g-3">
            <?php foreach (array_slice($testimonials, 0, 3) as $t) { ?>
            <div class="col-md-4">
                <blockquote class="rateb-mkt-testimonial">
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
                </blockquote>
            </div>
            <?php } ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo rateb_url('site/reviews'); ?>" class="btn btn-outline-primary rateb-mkt-more-btn">
                <i class="fas fa-circle-plus ms-1"></i><?php echo __('cms_view_all_reviews'); ?>
            </a>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($articles)) { ?>
<section class="rateb-mkt-section rateb-mkt-section-alt">
    <div class="container">
        <h2 class="rateb-mkt-section-title"><?php echo __('cms_latest_articles'); ?></h2>
        <div class="row g-3">
            <?php foreach ($articles as $article) { ?>
            <div class="col-md-4">
                <article class="rateb-mkt-article-card">
                    <h3><a href="<?php echo rateb_url('site/blog/' . ($article['slug'] ?? '')); ?>"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($article, 'title')); ?></a></h3>
                    <p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($article, 'excerpt')); ?></p>
                </article>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($faqs)) { ?>
<section class="rateb-mkt-section">
    <div class="container">
        <?php
        $sectionTitle = __('cms_faq_preview');
        $moreUrl = rateb_url('site/faq');
        require RATEB_ROOT . '/views/marketing/partials/section-head-more.php';
        ?>
        <div class="accordion rateb-mkt-faq" id="homeFaq">
            <?php foreach (array_slice($faqs, 0, 5) as $i => $faq) { ?>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button<?php echo $i > 0 ? ' collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $i; ?>">
                        <?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($faq, 'question')); ?>
                    </button>
                </h3>
                <div id="faq<?php echo $i; ?>" class="accordion-collapse collapse<?php echo $i === 0 ? ' show' : ''; ?>" data-bs-parent="#homeFaq">
                    <div class="accordion-body"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($faq, 'answer')); ?></div>
                </div>
            </div>
            <?php } ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo rateb_url('site/faq'); ?>" class="btn btn-outline-primary rateb-mkt-more-btn">
                <i class="fas fa-circle-plus ms-1"></i><?php echo __('cms_view_all_faq'); ?>
            </a>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['contact_cta']['section'])) {
    $cta = $content['contact_cta']['section']; ?>
<section class="rateb-mkt-cta">
    <div class="container text-center">
        <h2 class="rateb-mkt-section-title text-white mb-3"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($cta, 'title')); ?></h2>
        <?php if (trim(CmsService::pickLocale($cta, 'body')) !== '') { ?>
        <p class="mb-4 opacity-90"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($cta, 'body')); ?></p>
        <?php } ?>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="<?php echo rateb_url('site/request-demo'); ?>" class="btn btn-light btn-lg"><?php echo __('cms_request_demo'); ?></a>
            <a href="<?php echo rateb_url('site/contact'); ?>" class="btn btn-outline-light btn-lg"><?php echo __('cms_contact_us'); ?></a>
        </div>
    </div>
</section>
<?php } ?>
