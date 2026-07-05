<?php
declare(strict_types=1);

/** @var array<int, array<string, mixed>> $snapshots */
/** @var array<int, array<string, mixed>> $shifts */
$snapshots = $snapshots ?? [];
$shifts = $shifts ?? [];
?>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
<div class="rateb-pos-page">
    <h1 class="h3 mb-3"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></h1>

    <div class="rateb-card mb-4">
        <div class="rateb-card-header"><strong><?php echo __('pos_x_report'); ?></strong></div>
        <div class="rateb-card-body">
            <p class="text-muted small mb-2"><?php echo __('pos_x_report_hint'); ?></p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?php echo __('pos_shift_no'); ?></th>
                            <th><?php echo __('status'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($shifts as $shift) { ?>
                        <tr>
                            <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($shift['shift_no'] ?? '')); ?></td>
                            <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($shift['status'] ?? '')); ?></td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo rateb_app_url('pos/reports/shifts/' . (int) ($shift['id'] ?? 0) . '/x'); ?>">
                                    <?php echo __('pos_x_report'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if ($shifts === []) { ?>
                        <tr><td colspan="3" class="text-muted"><?php echo __('no_records'); ?></td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rateb-card">
        <div class="rateb-card-header"><strong><?php echo __('pos_z_report'); ?> — <?php echo __('pos_report_snapshots'); ?></strong></div>
        <div class="rateb-card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?php echo __('pos_report_no'); ?></th>
                            <th><?php echo __('pos_shift_no'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($snapshots as $row) { ?>
                        <tr>
                            <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($row['report_no'] ?? '')); ?></td>
                            <td>#<?php echo (int) ($row['shift_id'] ?? 0); ?></td>
                            <td><?php echo \Rateb\App\Core\View::formatDate((string) ($row['created_at'] ?? '')); ?></td>
                            <td class="text-end">
                                <?php if (($row['report_type'] ?? '') === 'z') { ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo rateb_app_url('pos/reports/snapshots/' . (int) ($row['id'] ?? 0) . '/z'); ?>">
                                    <?php echo __('view'); ?>
                                </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if ($snapshots === []) { ?>
                        <tr><td colspan="4" class="text-muted"><?php echo __('no_records'); ?></td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
