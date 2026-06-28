<?php
/** @var string $title */
/** @var array<int, array{name: string, total: float|int, suffix?: string}> $rows */
/** @var float|int $max */
$rows = $rows ?? [];
$max = max(1, (float) ($max ?? 1));
?>
<?php if ($rows !== []) { ?>
<div class="nx-glass">
    <div class="nx-glass__top">
        <span class="nx-glass__title"><?php echo Rateb\App\Core\View::escape($title); ?></span>
    </div>
    <div class="nx-glass__body">
        <ol class="nx-ranks">
            <?php foreach ($rows as $row) {
                $val = (float) ($row['total'] ?? 0);
                $pct = min(100, ($val / $max) * 100);
                $suffix = (string) ($row['suffix'] ?? '');
                ?>
            <li class="nx-rank">
                <div class="nx-rank__row">
                    <span class="nx-rank__name"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></span>
                    <span class="nx-rank__num"><?php echo number_format($val, 0); ?><?php echo $suffix !== '' ? ' ' . Rateb\App\Core\View::escape($suffix) : ''; ?></span>
                </div>
                <div class="nx-rank__track"><div class="nx-rank__fill" style="width:<?php echo $pct; ?>%"></div></div>
            </li>
            <?php } ?>
        </ol>
    </div>
</div>
<?php } ?>
