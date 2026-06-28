<?php
/** @var array<int, array<string, mixed>> $items */
/** @var string $cols */
$items = $items ?? [];
$cols = $cols ?? '';
?>
<?php if ($items !== []) { ?>
<div class="rdx-kpis<?php echo $cols === '4' ? ' rdx-kpis--4' : ''; ?>">
    <?php foreach ($items as $kpi) {
        $tone = (string) ($kpi['tone'] ?? 'blue');
        $trend = (string) ($kpi['trend'] ?? '');
        $trendDir = (string) ($kpi['trendDir'] ?? '');
        if ($trendDir === '' && $trend !== '') {
            $trendDir = str_starts_with($trend, '-') ? 'down' : 'up';
        }
        ?>
    <div class="rdx-kpi" data-tone="<?php echo Rateb\App\Core\View::escape($tone); ?>">
        <div class="rdx-kpi-val"><?php echo $kpi['value'] ?? '0'; ?></div>
        <div class="rdx-kpi-lbl"><?php echo Rateb\App\Core\View::escape((string) ($kpi['label'] ?? '')); ?></div>
        <?php if ($trend !== '') { ?>
        <div class="rdx-kpi-trend <?php echo Rateb\App\Core\View::escape($trendDir); ?>"><?php echo Rateb\App\Core\View::escape($trend); ?> <?php echo __('trend_from_last_month'); ?></div>
        <?php } ?>
    </div>
    <?php } ?>
</div>
<?php } ?>
