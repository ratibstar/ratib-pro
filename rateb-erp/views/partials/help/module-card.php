<?php
declare(strict_types=1);

/** @var array<string,mixed> $module */
$accent = preg_replace('/[^a-z]/', '', (string) ($module['accent'] ?? 'sky')) ?: 'sky';
$slug = (string) ($module['slug'] ?? '');
$url = rateb_url('admin/help/module/' . rawurlencode($slug));
$count = (int) ($module['article_count'] ?? 0);
$host = strtolower((string) ($module['host'] ?? 'all'));
$title = (string) ($module['title'] ?? '');
$desc = (string) ($module['description'] ?? '');
$hay = $title . ' ' . $desc . ' ' . $slug;
?>
<a class="hc-module-card hc-accent-<?php echo htmlspecialchars($accent, ENT_QUOTES, 'UTF-8'); ?>"
   href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
   data-hc-hay="<?php echo Rateb\App\Core\View::escape($hay); ?>">
    <span class="hc-module-card__icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars((string) ($module['icon'] ?? 'fa-circle-question'), ENT_QUOTES, 'UTF-8'); ?>"></i></span>
    <span class="hc-module-card__body">
        <span class="hc-module-card__title">
            <?php echo Rateb\App\Core\View::escape($title); ?>
            <?php if ($host === 'platform') { ?>
            <span class="hc-module-card__badge"><?php echo Rateb\App\Core\View::escape(__('help_platform_only')); ?></span>
            <?php } ?>
        </span>
        <span class="hc-module-card__desc"><?php echo Rateb\App\Core\View::escape($desc); ?></span>
        <span class="hc-module-card__meta">
            <span><?php echo Rateb\App\Core\View::escape(__('help_articles_count', ['count' => $count])); ?></span>
            <span class="hc-module-card__cta"><?php echo Rateb\App\Core\View::escape(__('help_explore')); ?> <i class="fas fa-arrow-left" aria-hidden="true"></i></span>
        </span>
    </span>
</a>
