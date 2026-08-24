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
<article class="hc-page hc-article-page hc-accent-<?php echo htmlspecialchars($accent, ENT_QUOTES, 'UTF-8'); ?>">
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
        <a class="hc-pager__link" href="<?php echo rateb_url('admin/help/article/' . rawurlencode((string) $article['prev']['slug'])); ?>">
            <span class="hc-pager__dir"><?php echo View::escape(__('help_prev')); ?></span>
            <span><?php echo View::escape((string) ($article['prev']['title'] ?? '')); ?></span>
        </a>
        <?php } else { ?>
        <span></span>
        <?php } ?>
        <?php if (!empty($article['next']) && is_array($article['next'])) { ?>
        <a class="hc-pager__link hc-pager__link--next" href="<?php echo rateb_url('admin/help/article/' . rawurlencode((string) $article['next']['slug'])); ?>">
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
