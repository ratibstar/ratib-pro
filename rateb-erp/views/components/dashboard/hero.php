<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array<string, mixed>> $actions */
/** @var array<int, array<string, mixed>> $metrics */
/** @var array<int, array{label: string, value: string}>|null $metaChips */
$actions = $actions ?? [];
$metrics = $metrics ?? [];
$metaChips = $metaChips ?? null;
?>
<section class="rp-hero" data-rp-dash="v3">
    <div class="rp-hero__row">
        <div class="rp-hero__text">
            <h1><?php echo Rateb\App\Core\View::escape($title); ?></h1>
            <?php if (!empty($subtitle)) { ?>
            <p><?php echo $subtitle; ?></p>
            <?php } ?>
            <?php if (is_array($metaChips) && $metaChips !== []) { ?>
            <div class="rp-meta">
                <?php foreach ($metaChips as $chip) { ?>
                <span class="rp-meta-chip"><span><?php echo Rateb\App\Core\View::escape((string) ($chip['label'] ?? '')); ?></span> <?php echo $chip['value'] ?? ''; ?></span>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
        <?php if ($actions !== []) { ?>
        <nav class="rp-toolbar" aria-label="<?php echo __('quick_shortcuts'); ?>">
            <?php foreach ($actions as $act) {
                $cls = 'rp-tool';
                if (!empty($act['primary'])) {
                    $cls .= ' rp-tool--accent';
                } elseif (!empty($act['ghost'])) {
                    $cls .= ' rp-tool--ghost';
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
    </div>
    <?php if ($metrics !== []) { ?>
    <div class="rp-metrics">
        <?php foreach ($metrics as $m) {
            $trend = (string) ($m['trend'] ?? '');
            $trendDir = (string) ($m['trendDir'] ?? '');
            if ($trendDir === '' && $trend !== '') {
                $trendDir = str_starts_with($trend, '-') ? 'down' : 'up';
            }
            ?>
        <div class="rp-metric" data-tone="<?php echo Rateb\App\Core\View::escape((string) ($m['tone'] ?? '')); ?>">
            <span class="rp-metric__val"><?php echo $m['value'] ?? '0'; ?></span>
            <span class="rp-metric__lbl"><?php echo Rateb\App\Core\View::escape((string) ($m['label'] ?? '')); ?></span>
            <?php if ($trend !== '') { ?>
            <span class="rp-metric__trend <?php echo Rateb\App\Core\View::escape($trendDir); ?>"><?php echo Rateb\App\Core\View::escape($trend); ?> <?php echo __('trend_from_last_month'); ?></span>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
    <?php } ?>
</section>
