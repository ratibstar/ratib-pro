<?php
declare(strict_types=1);

/** @var array<string,mixed> $module */
/** @var list<array<string,mixed>> $articles */
/** @var list<array<string,mixed>> $faqs */
/** @var string $helpHomeUrl */

use Rateb\App\Core\View;

$accent = preg_replace('/[^a-z]/', '', (string) ($module['accent'] ?? 'sky')) ?: 'sky';
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/help-center.css'); ?>">
<div class="hc-page hc-module-page hc-accent-<?php echo htmlspecialchars($accent, ENT_QUOTES, 'UTF-8'); ?>">
    <?php View::partial('help/breadcrumb', [
        'crumbs' => [
            ['label' => __('help_center'), 'url' => $helpHomeUrl],
            ['label' => (string) ($module['title'] ?? ''), 'url' => null],
        ],
    ]); ?>

    <header class="hc-module-hero">
        <span class="hc-module-hero__icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars((string) ($module['icon'] ?? 'fa-circle-question'), ENT_QUOTES, 'UTF-8'); ?>"></i></span>
        <div>
            <h2 class="hc-module-hero__title"><?php echo View::escape((string) ($module['title'] ?? '')); ?></h2>
            <p class="hc-module-hero__desc"><?php echo View::escape((string) ($module['description'] ?? '')); ?></p>
        </div>
    </header>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_overview')); ?></h3>
        <p><?php echo View::escape((string) ($module['overview'] ?? '')); ?></p>
        <?php if (!empty($module['flow']) && is_array($module['flow'])) { ?>
        <div class="hc-flow" aria-label="<?php echo View::escape(__('help_flow')); ?>">
            <?php foreach ($module['flow'] as $i => $step) { ?>
                <?php if ($i > 0) { ?><span class="hc-flow__sep" aria-hidden="true">→</span><?php } ?>
                <span class="hc-flow__step"><?php echo View::escape((string) $step); ?></span>
            <?php } ?>
        </div>
        <?php } ?>
    </section>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_start_here')); ?></h3>
        <ol class="hc-start-list">
            <?php foreach (($module['start_here'] ?? []) as $step) { ?>
            <li><?php echo View::escape((string) $step); ?></li>
            <?php } ?>
        </ol>
    </section>

    <section class="hc-section" aria-labelledby="hc-module-articles">
        <div class="hc-section__head">
            <h3 id="hc-module-articles"><?php echo View::escape(__('help_articles')); ?></h3>
        </div>
        <div class="hc-article-grid">
            <?php foreach ($articles as $article) {
                View::partial('help/article-card', ['article' => $article]);
            } ?>
        </div>
    </section>

    <?php if ($faqs !== []) { ?>
    <section class="hc-section">
        <div class="hc-section__head"><h3><?php echo View::escape(__('help_faq_title')); ?></h3></div>
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
