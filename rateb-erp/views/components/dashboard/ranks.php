<?php
/** @var string $title */
/** @var array<int, array{name: string, total: float|int, suffix?: string}> $rows */
/** @var float|int $max */
$rows = $rows ?? [];
$max = max(1, (float) ($max ?? 1));
?>
<?php if ($rows !== []) { ?>
<div class="rp-tile rp-tile--6">
    <div class="rp-tile__head"><?php echo Rateb\App\Core\View::escape($title); ?></div>
    <div class="rp-tile__body">
        <ol class="rp-ranks">
            <?php foreach ($rows as $row) {
                $val = (float) ($row['total'] ?? 0);
                $pct = min(100, ($val / $max) * 100);
                $suffix = (string) ($row['suffix'] ?? '');
                ?>
            <li class="rp-rank">
                <div class="rp-rank__top">
                    <span class="rp-rank__name"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></span>
                    <span class="rp-rank__val"><?php echo number_format($val, 0); ?><?php echo $suffix !== '' ? ' ' . Rateb\App\Core\View::escape($suffix) : ''; ?></span>
                </div>
                <div class="rp-rank__track"><div class="rp-rank__bar" style="width:<?php echo $pct; ?>%"></div></div>
            </li>
            <?php } ?>
        </ol>
    </div>
</div>
<?php } ?>
