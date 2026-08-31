<?php
declare(strict_types=1);

/** @var array<string,mixed> $article */
$slug = (string) ($article['slug'] ?? '');
$url = rateb_url('admin/help/article/' . rawurlencode($slug));
$difficulty = (string) ($article['difficulty'] ?? 'beginner');
$accent = preg_replace('/[^a-z]/', '', (string) ($article['accent'] ?? 'sky')) ?: 'sky';
$diffLabel = __('help_difficulty_' . $difficulty);
if ($diffLabel === 'help_difficulty_' . $difficulty) {
    $diffLabel = $difficulty;
}
?>
<article class="hc-article-card hc-accent-<?php echo htmlspecialchars($accent, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="hc-article-card__icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars((string) ($article['icon'] ?? 'fa-circle-question'), ENT_QUOTES, 'UTF-8'); ?>"></i></div>
    <div class="hc-article-card__body">
        <h4 class="hc-article-card__title"><?php echo Rateb\App\Core\View::escape((string) ($article['title'] ?? '')); ?></h4>
        <p class="hc-article-card__summary"><?php echo Rateb\App\Core\View::escape((string) ($article['summary'] ?? '')); ?></p>
        <div class="hc-article-card__meta">
            <span class="hc-badge hc-badge--<?php echo htmlspecialchars($difficulty, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Rateb\App\Core\View::escape($diffLabel); ?></span>
            <span><i class="far fa-clock" aria-hidden="true"></i> <?php echo (int) ($article['minutes'] ?? 3); ?> <?php echo Rateb\App\Core\View::escape(__('help_minutes')); ?></span>
        </div>
    </div>
    <a class="hc-article-card__open" data-hc-nav="1" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo Rateb\App\Core\View::escape(__('help_open_article')); ?>
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
</article>
