<?php
declare(strict_types=1);

/** @var array<string, mixed> $report */
/** @var array<string, mixed>|null $snapshot */
$report = $report ?? [];
$shift = is_array($report['shift'] ?? null) ? $report['shift'] : [];
$drawer = is_array($report['drawer_reconciliation'] ?? null) ? $report['drawer_reconciliation'] : [];
$sales = is_array($report['sales_summary'] ?? null) ? $report['sales_summary'] : [];
$returns = is_array($report['return_summary'] ?? null) ? $report['return_summary'] : [];
$payments = is_array($report['payment_summary'] ?? null) ? $report['payment_summary'] : [];
$refunds = is_array($report['refund_summary'] ?? null) ? $report['refund_summary'] : [];
$totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
$type = strtoupper((string) ($report['report_type'] ?? 'x'));
?>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
<div class="rateb-pos-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></h1>
        <span class="badge bg-secondary"><?php echo \Rateb\App\Pos\Support\PosView::escape($type); ?></span>
    </div>

    <div class="rateb-card mb-3">
        <div class="rateb-card-body">
            <dl class="row mb-0 rateb-pos-dl">
                <dt class="col-sm-4"><?php echo __('pos_report_no'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($report['report_no'] ?? '')); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_shift_no'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($shift['shift_no'] ?? '')); ?></dd>
                <dt class="col-sm-4"><?php echo __('generated_at'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Core\View::formatDate((string) ($report['generated_at'] ?? '')); ?></dd>
            </dl>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="rateb-card h-100">
                <div class="rateb-card-header"><?php echo __('pos_sales_summary'); ?></div>
                <div class="rateb-card-body">
                    <dl class="row mb-0 rateb-pos-dl">
                        <dt class="col-6"><?php echo __('pos_orders'); ?></dt>
                        <dd class="col-6"><?php echo (int) ($sales['count'] ?? 0); ?></dd>
                        <dt class="col-6"><?php echo __('total'); ?></dt>
                        <dd class="col-6"><?php echo number_format((float) ($sales['total'] ?? 0), 2); ?></dd>
                        <dt class="col-6"><?php echo __('tax'); ?></dt>
                        <dd class="col-6"><?php echo number_format((float) ($sales['tax'] ?? 0), 2); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="rateb-card h-100">
                <div class="rateb-card-header"><?php echo __('pos_return_summary'); ?></div>
                <div class="rateb-card-body">
                    <dl class="row mb-0 rateb-pos-dl">
                        <dt class="col-6"><?php echo __('pos_returns'); ?></dt>
                        <dd class="col-6"><?php echo (int) ($returns['count'] ?? 0); ?></dd>
                        <dt class="col-6"><?php echo __('total'); ?></dt>
                        <dd class="col-6"><?php echo number_format((float) ($returns['total'] ?? 0), 2); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="rateb-card mb-3">
        <div class="rateb-card-header"><?php echo __('pos_drawer_reconciliation'); ?></div>
        <div class="rateb-card-body">
            <dl class="row mb-0 rateb-pos-dl">
                <dt class="col-sm-4"><?php echo __('pos_opening_float'); ?></dt>
                <dd class="col-sm-8"><?php echo number_format((float) ($drawer['opening_float'] ?? 0), 2); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_expected_cash'); ?></dt>
                <dd class="col-sm-8"><?php echo number_format((float) ($drawer['expected_balance'] ?? 0), 2); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_closing_float'); ?></dt>
                <dd class="col-sm-8"><?php echo isset($drawer['counted_balance']) ? number_format((float) $drawer['counted_balance'], 2) : '—'; ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_cash_variance'); ?></dt>
                <dd class="col-sm-8"><?php echo number_format((float) ($drawer['variance'] ?? 0), 2); ?></dd>
            </dl>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="rateb-card h-100">
                <div class="rateb-card-header"><?php echo __('pos_payment_summary'); ?></div>
                <div class="rateb-card-body">
                    <?php
                    $byPay = is_array($payments['by_method'] ?? null) ? $payments['by_method'] : [];
                    foreach ($byPay as $method => $row) {
                        echo '<div class="d-flex justify-content-between"><span>' . \Rateb\App\Pos\Support\PosView::escape($method) . '</span><span>' . number_format((float) ($row['total'] ?? 0), 2) . '</span></div>';
                    }
                    if ($byPay === []) {
                        echo '<span class="text-muted">' . __('no_records') . '</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="rateb-card h-100">
                <div class="rateb-card-header"><?php echo __('pos_refund_summary'); ?></div>
                <div class="rateb-card-body">
                    <?php
                    $byRef = is_array($refunds['by_method'] ?? null) ? $refunds['by_method'] : [];
                    foreach ($byRef as $method => $row) {
                        echo '<div class="d-flex justify-content-between"><span>' . \Rateb\App\Pos\Support\PosView::escape($method) . '</span><span>' . number_format((float) ($row['total'] ?? 0), 2) . '</span></div>';
                    }
                    if ($byRef === []) {
                        echo '<span class="text-muted">' . __('no_records') . '</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="rateb-card mb-3">
        <div class="rateb-card-header"><?php echo __('pos_net_totals'); ?></div>
        <div class="rateb-card-body">
            <dl class="row mb-0 rateb-pos-dl">
                <dt class="col-sm-4"><?php echo __('pos_gross_sales'); ?></dt>
                <dd class="col-sm-8"><?php echo number_format((float) ($totals['gross_sales'] ?? 0), 2); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_net_sales'); ?></dt>
                <dd class="col-sm-8"><?php echo number_format((float) ($totals['net_sales'] ?? 0), 2); ?></dd>
            </dl>
        </div>
    </div>

    <a href="<?php echo rateb_app_url('pos/reports'); ?>" class="btn btn-outline-secondary"><?php echo __('back'); ?></a>
</div>
