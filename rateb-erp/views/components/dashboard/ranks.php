<?php
/** @var string $title */
/** @var array<int, array{name: string, total: float|int, suffix?: string}> $rows */
/** @var float|int $max */
$rows = $rows ?? [];
$max = max(1, (float) ($max ?? 1));
?>
<?php if ($rows !== []) { ?>
<div class="rdx-card">
    <div class="rdx-card-head"><?php echo Rateb\App\Core\View::escape($title); ?></div>
    <ol class="rdx-ranks">
        <?php foreach ($rows as $row) {
            $val = (float) ($row['total'] ?? 0);
            $pct = min(100, ($val / $max) * 100);
            $suffix = (string) ($row['suffix'] ?? '');
            ?>
        <li class="rdx-rank">
            <div class="rdx-rank-top">
                <span class="rdx-rank-name"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></span>
                <span class="rdx-rank-val"><?php echo is_float($val) && $val !== (int) $val ? number_format($val, 0) : (int) $val; ?><?php echo $suffix !== '' ? ' ' . Rateb\App\Core\View::escape($suffix) : ''; ?></span>
            </div>
            <div class="rdx-rank-track"><div class="rdx-rank-bar" style="width:<?php echo $pct; ?>%"></div></div>
        </li>
        <?php } ?>
    </ol>
</div>
<?php } ?>
