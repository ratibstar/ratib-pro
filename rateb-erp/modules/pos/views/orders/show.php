<?php
declare(strict_types=1);

/** @var array<int, array<string, mixed>> $refunds */
/** @var array<string, mixed>|null $receipt */
/** @var array<string, mixed> $order */
/** @var int $display_no */
/** @var array<int, array<string, mixed>> $lines */
/** @var array<int, array<string, mixed>> $payments */

$receipt = is_array($receipt ?? null) ? $receipt : null;
$completedAt = (string) ($order['completed_at'] ?? $receipt['completed_at'] ?? $order['created_at'] ?? '');
$paymentSum = 0.0;
foreach ($payments as $paymentRow) {
    $paymentSum += (float) ($paymentRow['amount'] ?? 0);
}
$orderTotal = (float) ($order['total'] ?? 0);
?>
<div class="rateb-pos-page">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo rateb_app_url('pos/orders'); ?>"><?php echo __('back'); ?></a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3"><?php echo __('pos_display_no'); ?></dt>
                <dd class="col-sm-9">#<?php echo (int) ($display_no ?? 0); ?></dd>
                <dt class="col-sm-3"><?php echo __('pos_order_no'); ?></dt>
                <dd class="col-sm-9"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($order['order_no'] ?? '')); ?></dd>
                <dt class="col-sm-3"><?php echo __('status'); ?></dt>
                <dd class="col-sm-9"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($order['status'] ?? '')); ?></dd>
                <dt class="col-sm-3"><?php echo __('pos_total'); ?></dt>
                <dd class="col-sm-9"><?php echo number_format($orderTotal, 2); ?></dd>
                <?php if ($completedAt !== ''): ?>
                    <dt class="col-sm-3"><?php echo __('created_at'); ?></dt>
                    <dd class="col-sm-9"><?php echo \Rateb\App\Pos\Support\PosView::escape($completedAt); ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><?php echo __('pos_cart_lines'); ?></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo __('description'); ?></th>
                        <th><?php echo __('quantity'); ?></th>
                        <th><?php echo __('pos_unit_price'); ?></th>
                        <th><?php echo __('pos_discount_total'); ?></th>
                        <th><?php echo __('pos_tax'); ?></th>
                        <th><?php echo __('pos_total'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr>
                            <td><?php echo (int) ($line['line_no'] ?? 0); ?></td>
                            <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($line['description'] ?? '')); ?></td>
                            <td><?php echo number_format((float) ($line['quantity'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($line['unit_price'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($line['discount_amount'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($line['tax_amount'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($line['line_total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($payments !== []): ?>
        <div class="card mb-3">
            <div class="card-header"><?php echo __('pos_payment_method'); ?></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?php echo __('pos_payment_method'); ?></th>
                            <th><?php echo __('pos_payment_amount'); ?></th>
                            <th><?php echo __('pos_payment_reference'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($payment['payment_method'] ?? '')); ?></td>
                                <td><?php echo number_format((float) ($payment['amount'] ?? 0), 2); ?></td>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($payment['reference_no'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (abs($paymentSum - $orderTotal) > 0.009): ?>
                <div class="card-footer small text-muted">
                    <?php echo __('pos_total'); ?>: <?php echo number_format($orderTotal, 2); ?>
                    ·
                    <?php echo __('pos_payment_amount'); ?>: <?php echo number_format($paymentSum, 2); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($refunds)): ?>
        <div class="card mb-3">
            <div class="card-header"><?php echo __('pos_refund_method'); ?></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?php echo __('pos_refund_method'); ?></th>
                            <th><?php echo __('pos_payment_amount'); ?></th>
                            <th><?php echo __('pos_payment_reference'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($refunds as $refund): ?>
                            <tr>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($refund['refund_method'] ?? '')); ?></td>
                                <td><?php echo number_format((float) ($refund['amount'] ?? 0), 2); ?></td>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($refund['reference_no'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($receipt !== null): ?>
        <div class="card mb-3">
            <div class="card-header"><?php echo __('pos_receipt'); ?></div>
            <div class="card-body">
                <dl class="rateb-pos-order-receipt-summary">
                    <div>
                        <dt><?php echo __('pos_order_no'); ?></dt>
                        <dd><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($receipt['order_no'] ?? $order['order_no'] ?? '')); ?></dd>
                    </div>
                    <div>
                        <dt><?php echo __('pos_total'); ?></dt>
                        <dd><?php echo number_format((float) ($receipt['total'] ?? $orderTotal), 2); ?></dd>
                    </div>
                    <div>
                        <dt><?php echo __('created_at'); ?></dt>
                        <dd><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($receipt['completed_at'] ?? $completedAt)); ?></dd>
                    </div>
                    <div>
                        <dt><?php echo __('pos_cart_lines'); ?></dt>
                        <dd><?php echo is_array($receipt['lines'] ?? null) ? count($receipt['lines']) : count($lines); ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    <?php endif; ?>
</div>
