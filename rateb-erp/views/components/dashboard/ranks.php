<?php
/** @var string $title */
/** @var array<int, array{name: string, total: float|int, suffix?: string}> $rows */
$rows = $rows ?? [];
?>
<?php if ($rows !== []) { ?>
<section class="cm-board">
    <div class="cm-board__head"><?php echo Rateb\App\Core\View::escape($title); ?></div>
    <div class="cm-podium">
        <?php foreach ($rows as $i => $row) {
            $val = (float) ($row['total'] ?? 0);
            $suffix = (string) ($row['suffix'] ?? '');
            $rank = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            ?>
        <article class="cm-podium__card">
            <span class="cm-podium__rank"><?php echo $rank; ?></span>
            <span class="cm-podium__name"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></span>
            <span class="cm-podium__score"><?php echo number_format($val, 0); ?><?php echo $suffix !== '' ? ' ' . Rateb\App\Core\View::escape($suffix) : ''; ?></span>
        </article>
        <?php } ?>
    </div>
</section>
<?php } ?>
