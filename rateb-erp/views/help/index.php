<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $modules */
/** @var list<array<string,mixed>> $searchIndex */
/** @var list<array<string,mixed>> $searchHits */
/** @var string $searchQuery */
/** @var list<array<string,mixed>> $faqs */
/** @var bool $canManage */
/** @var string $helpHomeUrl */

use Rateb\App\Core\View;

$searchQuery = trim((string) ($searchQuery ?? ''));
$searchHits = is_array($searchHits ?? null) ? $searchHits : [];
$qNorm = function_exists('mb_strtolower') ? mb_strtolower($searchQuery) : strtolower($searchQuery);
$moduleMatchCount = 0;
if ($qNorm !== '') {
    foreach ($modules as $modRow) {
        $hay = ($modRow['title'] ?? '') . ' ' . ($modRow['description'] ?? '') . ' ' . ($modRow['slug'] ?? '');
        $hayNorm = function_exists('mb_strtolower') ? mb_strtolower((string) $hay) : strtolower((string) $hay);
        if ($hayNorm !== '' && (function_exists('mb_strpos') ? mb_strpos($hayNorm, $qNorm) : strpos($hayNorm, $qNorm)) !== false) {
            $moduleMatchCount++;
        }
    }
}
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/help-center.css'); ?>">
<?php
$hcDir = (function_exists('rateb_locale') && rateb_locale() === 'en') ? 'ltr' : 'rtl';
$hcLang = $hcDir === 'ltr' ? 'en' : 'ar';
?>
<div class="hc-page" id="rateb-help-center"
     data-hc-home="<?php echo View::escape($helpHomeUrl); ?>"
     data-hc-search-url="<?php echo View::escape(rateb_url('admin/help/api/search')); ?>"
     data-hc-lang="<?php echo View::escape($hcLang); ?>" dir="<?php echo View::escape($hcDir); ?>">
    <section class="hc-hero" aria-labelledby="hc-hero-title">
        <p class="hc-hero__eyebrow"><?php echo View::escape(__('help_center')); ?></p>
        <h2 id="hc-hero-title" class="hc-hero__title"><?php echo View::escape(__('help_hero_title')); ?></h2>
        <p class="hc-hero__subtitle"><?php echo View::escape(__('help_hero_subtitle')); ?></p>

        <?php View::partial('help/search-bar', [
            'searchQuery' => $searchQuery,
            'searchHits' => $searchHits,
            'hcSearchCompact' => false,
        ]); ?>

        <?php if (!empty($canManage)) { ?>
        <div class="hc-hero__admin">
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_url('admin/help/manage'); ?>">
                <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                <?php echo View::escape(__('help_admin_title')); ?>
            </a>
        </div>
        <?php } ?>
    </section>

    <?php
    $appOffers = is_array($appOffers ?? null) ? $appOffers : [];
    $appPages = is_array($appPages ?? null) ? $appPages : [];
    $pickLang = static function (string $ar, string $en) use ($hcLang): string {
        return $hcLang === 'en' ? ($en !== '' ? $en : $ar) : ($ar !== '' ? $ar : $en);
    };
    ?>
    <?php if ($appOffers !== []) { ?>
    <section class="hc-section" aria-labelledby="hc-offers-title">
        <div class="hc-section__head">
            <h3 id="hc-offers-title"><?php echo View::escape(__('mobile_apps_offers_title')); ?></h3>
        </div>
        <div class="row g-3">
            <?php foreach ($appOffers as $offer) {
                $oTitle = $pickLang((string) ($offer['title_ar'] ?? ''), (string) ($offer['title_en'] ?? ''));
                $oBody = $pickLang((string) ($offer['body_ar'] ?? ''), (string) ($offer['body_en'] ?? ''));
                $oImage = (string) ($offer['image'] ?? '');
                $oBadge = (string) ($offer['discount_label'] ?? '');
                ?>
            <div class="col-md-6 col-lg-4">
                <div class="rateb-card h-100">
                    <?php if ($oImage !== '') { ?>
                    <img src="<?php echo View::escape($oImage); ?>" alt="" class="w-100" style="max-height:160px;object-fit:cover" loading="lazy">
                    <?php } ?>
                    <div class="rateb-card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <strong><?php echo View::escape($oTitle); ?></strong>
                            <?php if ($oBadge !== '') { ?><span class="badge text-bg-danger"><?php echo View::escape($oBadge); ?></span><?php } ?>
                        </div>
                        <?php if ($oBody !== '') { ?><p class="small text-muted mb-0 mt-2"><?php echo nl2br(View::escape($oBody)); ?></p><?php } ?>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </section>
    <?php } ?>

    <?php if ($appPages !== []) { ?>
    <section class="hc-section" aria-labelledby="hc-app-pages-title">
        <div class="hc-section__head">
            <h3 id="hc-app-pages-title"><?php echo View::escape(__('mobile_apps_app_pages_title')); ?></h3>
        </div>
        <div class="hc-faq-list">
            <?php foreach ($appPages as $page) {
                $pTitle = $pickLang((string) $page['title_ar'], (string) $page['title_en']);
                if ($pTitle === '') {
                    $pTitle = __('agent_apps_slug_' . (string) $page['slug']);
                }
                $pBody = $pickLang((string) $page['body_ar'], (string) $page['body_en']);
                if ($pBody === '') {
                    continue;
                }
                ?>
            <details class="hc-faq">
                <summary><?php echo View::escape($pTitle); ?></summary>
                <p><?php echo nl2br(View::escape($pBody)); ?></p>
            </details>
            <?php } ?>
        </div>
    </section>
    <?php } ?>

    <section class="hc-section" aria-labelledby="hc-modules-title">
        <div class="hc-section__head">
            <h3 id="hc-modules-title"><?php echo View::escape(__('help_modules_title')); ?></h3>
            <p><?php echo View::escape(__('help_modules_subtitle')); ?></p>
        </div>
        <div class="hc-module-grid">
            <?php foreach ($modules as $module) {
                $hay = (string) (($module['title'] ?? '') . ' ' . ($module['description'] ?? '') . ' ' . ($module['slug'] ?? ''));
                $hayNorm = function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay);
                $isMatch = $qNorm === '' || (function_exists('mb_strpos') ? mb_strpos($hayNorm, $qNorm) : strpos($hayNorm, $qNorm)) !== false;
                $module['search_hidden'] = $qNorm !== '' && $moduleMatchCount > 0 && !$isMatch;
                Rateb\App\Core\View::partial('help/module-card', ['module' => $module]);
            } ?>
        </div>
    </section>

    <?php if ($faqs !== []) { ?>
    <section class="hc-section" aria-labelledby="hc-faq-title">
        <div class="hc-section__head">
            <h3 id="hc-faq-title"><?php echo View::escape(__('help_faq_title')); ?></h3>
        </div>
        <div class="hc-faq-list">
            <?php foreach ($faqs as $faq) { ?>
            <details class="hc-faq">
                <summary><?php echo View::escape((string) ($faq['question'] ?? '')); ?></summary>
                <p><?php echo View::escape((string) ($faq['answer'] ?? '')); ?></p>
            </details>
            <?php } ?>
        </div>
    </section>
    <?php } ?>
</div>
<script type="application/json" id="hc-search-index"><?php echo json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<script src="<?php echo rateb_asset('js/help-center.js'); ?>" defer></script>
