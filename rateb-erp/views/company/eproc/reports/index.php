<?php
declare(strict_types=1);
/** @var array<string, array<string, int>> $board */
/** @var array<string, mixed> $spend */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('eproc_reports')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <h2 class="h5 mb-3"><?php echo htmlspecialchars(__('eproc_board'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php foreach (($board ?? []) as $entity => $counts): ?>
    <div class="mb-3">
        <div class="text-muted small mb-2"><?php echo htmlspecialchars((string) $entity, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="row g-3">
            <?php foreach ((array) $counts as $st => $cnt): ?>
            <div class="col-6 col-md-3 col-xl">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="fs-4 fw-semibold"><?php echo (int) $cnt; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if ((array) $counts === []): ?>
            <div class="col-12"><div class="text-muted small"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></div></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (($board ?? []) === []): ?>
    <p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <h2 class="h5 mt-4 mb-3"><?php echo htmlspecialchars(__('eproc_spend'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="border rounded p-3 mb-3 col-md-4">
        <div class="text-muted small"><?php echo htmlspecialchars(__('total'), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="fs-4 fw-semibold"><?php echo htmlspecialchars(number_format((float) ($spend['snapshots_total'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="table-responsive border rounded">
        <table class="table mb-0 align-middle">
            <thead><tr><th><?php echo htmlspecialchars(__('period'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('total'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach (($spend['snapshots_by_period'] ?? []) as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['period_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(number_format((float) ($row['total'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($spend['snapshots_by_period'] ?? []) === []): ?><tr><td colspan="2" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
