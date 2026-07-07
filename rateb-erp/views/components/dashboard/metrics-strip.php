<?php
/** @var array<int, array<string, mixed>> $metrics */
$metrics = $metrics ?? [];
?>
<?php if ($metrics !== []) { ?>
<div class="cm-strip" aria-label="<?php echo __('key_metrics'); ?>">
    <?php foreach ($metrics as $m) {
        $trend = (string) ($m['trend'] ?? '');
        $trendDir = (string) ($m['trendDir'] ?? '');
        if ($trendDir === '' && $trend !== '') {
            $trendDir = str_starts_with($trend, '-') ? 'down' : 'up';
        }
        ?>
    <article class="cm-strip__item"
             data-tone="<?php echo Rateb\App\Core\View::escape((string) ($m['tone'] ?? 'blue')); ?>"
             <?php if (!empty($m['key'])) { ?>data-stat-key="<?php echo Rateb\App\Core\View::escape((string) $m['key']); ?>"<?php } ?>>
        <span class="cm-strip__lbl"><?php echo Rateb\App\Core\View::escape((string) ($m['label'] ?? '')); ?></span>
        <span class="cm-strip__val"><?php echo $m['value'] ?? '0'; ?></span>
        <?php if ($trend !== '') { ?>
        <span class="cm-strip__trend <?php echo Rateb\App\Core\View::escape($trendDir); ?>"><?php echo Rateb\App\Core\View::escape($trend); ?></span>
        <?php } ?>
    </article>
    <?php } ?>
</div>
<?php } ?>
