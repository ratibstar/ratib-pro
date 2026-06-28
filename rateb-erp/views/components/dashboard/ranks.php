<?php
/** @var string $title */
/** @var array<int, array{name: string, total: float|int, suffix?: string}> $rows */
/** @var float|int|null $max */
$rows = $rows ?? [];
$computedMax = 1.0;
foreach ($rows as $r) {
    $computedMax = max($computedMax, (float) ($r['total'] ?? 0));
}
$max = (float) ($max ?? $computedMax);
?>
<section class="cm-board cm-board--fill">
    <div class="cm-board__head"><?php echo Rateb\App\Core\View::escape($title); ?></div>
    <?php if ($rows === []) { ?>
    <p class="cm-empty"><?php echo __('no_records'); ?></p>
    <?php } else { ?>
    <ol class="cm-leader">
        <?php foreach ($rows as $i => $row) {
            $val = (float) ($row['total'] ?? 0);
            $suffix = (string) ($row['suffix'] ?? '');
            $pct = min(100, ($val / $max) * 100);
            ?>
        <li class="cm-leader__row">
            <span class="cm-leader__rank"><?php echo $i + 1; ?></span>
            <span class="cm-leader__name"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></span>
            <span class="cm-leader__track" aria-hidden="true"><span class="cm-leader__fill" style="width:<?php echo $pct; ?>%"></span></span>
            <span class="cm-leader__score"><?php echo number_format($val, 0); ?><?php echo $suffix !== '' ? ' ' . Rateb\App\Core\View::escape($suffix) : ''; ?></span>
        </li>
        <?php } ?>
    </ol>
    <?php } ?>
</section>
