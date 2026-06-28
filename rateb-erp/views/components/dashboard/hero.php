<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $eyebrow */
/** @var array<int, array<string, mixed>> $actions */
/** @var array<int, array<string, mixed>> $metrics */
/** @var array<int, array{label: string, value: string}>|null $metaChips */
$actions = $actions ?? [];
$metrics = $metrics ?? [];
$metaChips = $metaChips ?? null;
$eyebrow = $eyebrow ?? __('dashboard');
?>
<header class="nx-header">
    <div class="nx-header__main">
        <span class="nx-eyebrow"><?php echo Rateb\App\Core\View::escape($eyebrow); ?></span>
        <h1><?php echo Rateb\App\Core\View::escape($title); ?></h1>
        <?php if (!empty($subtitle)) { ?>
        <p><?php echo $subtitle; ?></p>
        <?php } ?>
        <?php if (is_array($metaChips) && $metaChips !== []) { ?>
        <div class="nx-chips">
            <?php foreach ($metaChips as $chip) { ?>
            <span class="nx-chip"><em><?php echo Rateb\App\Core\View::escape((string) ($chip['label'] ?? '')); ?></em><?php echo $chip['value'] ?? ''; ?></span>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
    <?php if ($actions !== []) { ?>
    <nav class="nx-dock" aria-label="<?php echo __('quick_shortcuts'); ?>">
        <?php foreach ($actions as $act) {
            $cls = 'nx-act';
            if (!empty($act['primary'])) {
                $cls .= ' nx-act--hot';
            }
            $icon = !empty($act['icon']) ? '<i class="fas ' . Rateb\App\Core\View::escape((string) $act['icon']) . '"></i>' : '';
            if (!empty($act['form'])) {
                ?>
        <form method="post" action="<?php echo Rateb\App\Core\View::escape((string) $act['href']); ?>" class="d-inline m-0">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape((string) ($act['csrf'] ?? '')); ?>">
            <button type="submit" class="<?php echo $cls; ?>"><?php echo $icon; ?> <?php echo Rateb\App\Core\View::escape((string) $act['label']); ?></button>
        </form>
                <?php
                continue;
            }
            ?>
        <a href="<?php echo Rateb\App\Core\View::escape((string) $act['href']); ?>" class="<?php echo $cls; ?>"><?php echo $icon; ?> <?php echo Rateb\App\Core\View::escape((string) $act['label']); ?></a>
        <?php } ?>
    </nav>
    <?php } ?>
</header>
<?php if ($metrics !== []) { ?>
<div class="nx-stats<?php echo !empty($metricsCols) && $metricsCols === '4' ? ' nx-stats--4' : ''; ?>">
    <?php foreach ($metrics as $m) {
        $trend = (string) ($m['trend'] ?? '');
        $trendDir = (string) ($m['trendDir'] ?? '');
        if ($trendDir === '' && $trend !== '') {
            $trendDir = str_starts_with($trend, '-') ? 'down' : 'up';
        }
        ?>
    <article class="nx-stat" data-tone="<?php echo Rateb\App\Core\View::escape((string) ($m['tone'] ?? 'blue')); ?>">
        <span class="nx-stat__lbl"><?php echo Rateb\App\Core\View::escape((string) ($m['label'] ?? '')); ?></span>
        <span class="nx-stat__val"><?php echo $m['value'] ?? '0'; ?></span>
        <?php if ($trend !== '') { ?>
        <span class="nx-stat__trend <?php echo Rateb\App\Core\View::escape($trendDir); ?>"><?php echo Rateb\App\Core\View::escape($trend); ?> <?php echo __('trend_from_last_month'); ?></span>
        <?php } ?>
    </article>
    <?php } ?>
</div>
<?php } ?>
