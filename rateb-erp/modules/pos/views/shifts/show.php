<?php
declare(strict_types=1);

/** @var array<string, mixed> $shift */
/** @var bool $canClose */
$shift = $shift ?? [];
?>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
<div class="rateb-pos-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></h1>
        <?php if (!empty($canClose)) { ?>
        <a href="<?php echo rateb_app_url('pos/shifts/' . (int) ($shift['id'] ?? 0) . '/close'); ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-stop"></i> <?php echo __('pos_shift_close'); ?>
        </a>
        <?php } ?>
        <?php if (function_exists('rateb_can') && rateb_can('pos.reports.view')) { ?>
        <a href="<?php echo rateb_app_url('pos/reports/shifts/' . (int) ($shift['id'] ?? 0) . '/x'); ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-chart-bar"></i> <?php echo __('pos_x_report'); ?>
        </a>
        <?php } ?>
    </div>
    <div class="rateb-card">
        <div class="rateb-card-body">
            <dl class="row mb-0 rateb-pos-dl">
                <dt class="col-sm-4"><?php echo __('pos_shift_no'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($shift['shift_no'] ?? '')); ?></dd>
                <dt class="col-sm-4"><?php echo __('status'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($shift['status'] ?? '')); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_opening_float'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($shift['opening_float'] ?? '0')); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_closing_float'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($shift['closing_float'] ?? '—')); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_variance'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($shift['variance'] ?? '—')); ?></dd>
                <dt class="col-sm-4"><?php echo __('opened_at'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Core\View::formatDate((string) ($shift['opened_at'] ?? '')); ?></dd>
                <dt class="col-sm-4"><?php echo __('closed_at'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Core\View::formatDate((string) ($shift['closed_at'] ?? '—')); ?></dd>
            </dl>
        </div>
    </div>
    <a href="<?php echo rateb_app_url('pos/shifts'); ?>" class="btn btn-outline-secondary mt-3"><?php echo __('back'); ?></a>
</div>
