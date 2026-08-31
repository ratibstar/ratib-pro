<?php
declare(strict_types=1);

/** @var array<string,mixed> $article */
/** @var string $helpHomeUrl */

use Rateb\App\Core\View;

$moduleMeta = is_array($article['module_meta'] ?? null) ? $article['module_meta'] : null;
$moduleSlug = (string) ($article['module'] ?? '');
$moduleTitle = is_array($moduleMeta) ? (string) ($moduleMeta['title'] ?? $moduleSlug) : $moduleSlug;
$moduleUrl = $moduleSlug !== '' ? rateb_url('admin/help/module/' . rawurlencode($moduleSlug)) : $helpHomeUrl;
$difficulty = (string) ($article['difficulty'] ?? 'beginner');
$accent = preg_replace('/[^a-z]/', '', (string) ($article['accent'] ?? ($moduleMeta['accent'] ?? 'sky'))) ?: 'sky';
$diffLabel = __('help_difficulty_' . $difficulty);
if ($diffLabel === 'help_difficulty_' . $difficulty) {
    $diffLabel = $difficulty;
}
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/help-center.css'); ?>">
<?php
$hcDir = (function_exists('rateb_locale') && rateb_locale() === 'en') ? 'ltr' : 'rtl';
$hcLang = $hcDir === 'ltr' ? 'en' : 'ar';
$searchQuery = trim((string) ($searchQuery ?? ''));
$searchHits = is_array($searchHits ?? null) ? $searchHits : [];
$searchIndex = is_array($searchIndex ?? null) ? $searchIndex : [];
?>
<article class="hc-page hc-article-page hc-accent-<?php echo htmlspecialchars($accent, ENT_QUOTES, 'UTF-8'); ?>"
         id="rateb-help-center"
         data-hc-home="<?php echo View::escape($helpHomeUrl); ?>"
         data-hc-search-url="<?php echo View::escape(rateb_url('admin/help/api/search')); ?>"
         data-hc-lang="<?php echo View::escape($hcLang); ?>"
         dir="<?php echo htmlspecialchars($hcDir, ENT_QUOTES, 'UTF-8'); ?>">
    <?php View::partial('help/search-bar', [
        'searchQuery' => $searchQuery,
        'searchHits' => $searchHits,
        'hcSearchCompact' => true,
    ]); ?>
    <?php View::partial('help/breadcrumb', [
        'crumbs' => [
            ['label' => __('help_center'), 'url' => $helpHomeUrl],
            ['label' => $moduleTitle, 'url' => $moduleUrl],
            ['label' => (string) ($article['title'] ?? ''), 'url' => null],
        ],
    ]); ?>

    <header class="hc-article-hero">
        <span class="hc-article-hero__icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars((string) ($article['icon'] ?? 'fa-circle-question'), ENT_QUOTES, 'UTF-8'); ?>"></i></span>
        <div>
            <h2><?php echo View::escape((string) ($article['title'] ?? '')); ?></h2>
            <div class="hc-article-hero__meta">
                <span class="hc-badge hc-badge--<?php echo htmlspecialchars($difficulty, ENT_QUOTES, 'UTF-8'); ?>"><?php echo View::escape($diffLabel); ?></span>
                <span><i class="far fa-clock" aria-hidden="true"></i> <?php echo (int) ($article['minutes'] ?? 3); ?> <?php echo View::escape(__('help_minutes')); ?></span>
            </div>
        </div>
    </header>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_what')); ?></h3>
        <p><?php echo View::escape((string) ($article['what'] ?? '')); ?></p>
    </section>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_when')); ?></h3>
        <p><?php echo View::escape((string) ($article['when'] ?? '')); ?></p>
    </section>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_steps')); ?></h3>
        <ol class="hc-steps">
            <?php foreach (($article['steps'] ?? []) as $step) { ?>
            <li><?php echo View::escape((string) $step); ?></li>
            <?php } ?>
        </ol>
    </section>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_example')); ?></h3>
        <p><?php echo View::escape((string) ($article['example'] ?? '')); ?></p>
    </section>

    <section class="hc-panel hc-panel--split">
        <div>
            <h3><?php echo View::escape(__('help_tips')); ?></h3>
            <ul>
                <?php foreach (($article['tips'] ?? []) as $tip) { ?>
                <li><?php echo View::escape((string) $tip); ?></li>
                <?php } ?>
            </ul>
        </div>
        <div>
            <h3><?php echo View::escape(__('help_mistakes')); ?></h3>
            <ul>
                <?php foreach (($article['mistakes'] ?? []) as $mistake) { ?>
                <li><?php echo View::escape((string) $mistake); ?></li>
                <?php } ?>
            </ul>
        </div>
    </section>

    <nav class="hc-pager" aria-label="<?php echo View::escape(__('help_pager')); ?>">
        <?php if (!empty($article['prev']) && is_array($article['prev'])) { ?>
        <a class="hc-pager__link" data-hc-nav="1" href="<?php echo rateb_url('admin/help/article/' . rawurlencode((string) $article['prev']['slug'])); ?>">
            <span class="hc-pager__dir"><?php echo View::escape(__('help_prev')); ?></span>
            <span><?php echo View::escape((string) ($article['prev']['title'] ?? '')); ?></span>
        </a>
        <?php } else { ?>
        <span></span>
        <?php } ?>
        <?php if (!empty($article['next']) && is_array($article['next'])) { ?>
        <a class="hc-pager__link hc-pager__link--next" data-hc-nav="1" href="<?php echo rateb_url('admin/help/article/' . rawurlencode((string) $article['next']['slug'])); ?>">
            <span class="hc-pager__dir"><?php echo View::escape(__('help_next')); ?></span>
            <span><?php echo View::escape((string) ($article['next']['title'] ?? '')); ?></span>
        </a>
        <?php } ?>
    </nav>

    <?php if (!empty($article['related']) && is_array($article['related'])) { ?>
    <section class="hc-section">
        <div class="hc-section__head"><h3><?php echo View::escape(__('help_related')); ?></h3></div>
        <div class="hc-article-grid">
            <?php foreach ($article['related'] as $related) {
                View::partial('help/article-card', ['article' => $related]);
            } ?>
        </div>
    </section>
    <?php } ?>
</article>
<script type="application/json" id="hc-search-index"><?php echo json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<script src="<?php echo rateb_asset('js/help-center.js'); ?>" defer></script>
