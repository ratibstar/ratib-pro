<?php
/** @var array<int, array<string, mixed>> $metrics */
/** @var array<int, array{label: string, value: string}>|null $footer */
$metrics = $metrics ?? [];
$footer = $footer ?? null;
?>
<?php if ($metrics !== []) { ?>
<aside class="cm-rail">
    <div class="cm-rail__head"><?php echo __('key_metrics'); ?></div>
    <ul class="cm-metrics">
        <?php foreach ($metrics as $m) {
            $trend = (string) ($m['trend'] ?? '');
            $trendDir = (string) ($m['trendDir'] ?? '');
            if ($trendDir === '' && $trend !== '') {
                $trendDir = str_starts_with($trend, '-') ? 'down' : 'up';
            }
            ?>
        <li class="cm-metric" data-tone="<?php echo Rateb\App\Core\View::escape((string) ($m['tone'] ?? 'blue')); ?>">
            <span class="cm-metric__lbl"><?php echo Rateb\App\Core\View::escape((string) ($m['label'] ?? '')); ?></span>
            <span class="cm-metric__val"><?php echo $m['value'] ?? '0'; ?></span>
            <?php if ($trend !== '') { ?>
            <span class="cm-metric__trend <?php echo Rateb\App\Core\View::escape($trendDir); ?>"><?php echo Rateb\App\Core\View::escape($trend); ?> <?php echo __('trend_from_last_month'); ?></span>
            <?php } ?>
        </li>
        <?php } ?>
    </ul>
    <?php if (is_array($footer) && $footer !== []) { ?>
    <dl class="cm-rail__foot">
        <?php foreach ($footer as $row) { ?>
        <dt><?php echo Rateb\App\Core\View::escape((string) ($row['label'] ?? '')); ?></dt>
        <dd><?php echo $row['value'] ?? ''; ?></dd>
        <?php } ?>
    </dl>
    <?php } ?>
</aside>
<?php } ?>
