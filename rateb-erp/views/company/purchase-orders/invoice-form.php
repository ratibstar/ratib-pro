<?php
/** @var array<string, mixed> $order */
/** @var array<string, mixed>|null $invoice */
/** @var array<string, mixed> $lookups */
$invoice = $invoice ?? [];
$currency = (string) ($order['currency'] ?? 'SAR');
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/procurement-show.css'); ?>">

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-invoice-dollar me-1"></i> <?php echo __('purchase_invoice'); ?></span>
        <a href="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($order['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary">
            <?php echo __('cancel'); ?>
        </a>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small mb-3"><?php echo __('purchase_invoice_landed_costs_help'); ?></p>
        <div class="alert alert-light border small mb-3">
            <?php echo __('order_no'); ?>: <strong><?php echo Rateb\App\Core\View::escape((string) ($order['order_no'] ?? '')); ?></strong>
        </div>
        <form method="post" action="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($order['id'] ?? 0) . '/invoice'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="f_invoice_no"><?php echo __('purchase_invoice_no'); ?></label>
                    <input class="form-control" type="text" id="f_invoice_no" name="invoice_no"
                           value="<?php echo Rateb\App\Core\View::escape((string) ($invoice['invoice_no'] ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="f_invoice_date"><?php echo __('invoice_date'); ?></label>
                    <input class="form-control" type="date" id="f_invoice_date" name="invoice_date" dir="ltr" lang="en"
                           value="<?php echo Rateb\App\Core\View::escape((string) ($invoice['invoice_date'] ?? date('Y-m-d'))); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="f_status"><?php echo __('status'); ?></label>
                    <select class="form-select" id="f_status" name="status">
                        <option value="draft"<?php echo ($invoice['status'] ?? '') === 'draft' ? ' selected' : ''; ?>><?php echo __('draft'); ?></option>
                        <option value="posted"<?php echo ($invoice['status'] ?? '') === 'posted' ? ' selected' : ''; ?>><?php echo __('posted'); ?></option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="f_shipping_amount"><?php echo __('shipping'); ?></label>
                    <input class="form-control rateb-ltr-num" type="number" step="0.01" min="0" id="f_shipping_amount" name="shipping_amount"
                           value="<?php echo Rateb\App\Core\View::escape((string) ($invoice['shipping_amount'] ?? 0)); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="f_customs_clearance_amount"><?php echo __('customs_clearance_costs'); ?></label>
                    <input class="form-control rateb-ltr-num" type="number" step="0.01" min="0" id="f_customs_clearance_amount" name="customs_clearance_amount"
                           value="<?php echo Rateb\App\Core\View::escape((string) ($invoice['customs_clearance_amount'] ?? 0)); ?>">
                </div>
                <?php Rateb\App\Core\View::partial('procurement-customs-fields', [
                    'item' => $invoice,
                    'lookups' => $lookups,
                ]); ?>
                <div class="col-12">
                    <label class="form-label" for="f_notes"><?php echo __('notes'); ?></label>
                    <textarea class="form-control" id="f_notes" name="notes" rows="2"><?php echo Rateb\App\Core\View::escape((string) ($invoice['notes'] ?? '')); ?></textarea>
                </div>
                <div class="col-12">
                    <div class="rateb-card bg-light border-0">
                        <div class="rateb-card-body py-2 d-flex flex-wrap gap-4">
                            <span><?php echo __('subtotal'); ?>: <strong><?php echo number_format((float) ($invoice['line_subtotal'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></strong></span>
                            <span><?php echo __('total'); ?>: <strong><?php echo number_format((float) ($invoice['total_amount'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($order['id'] ?? 0)); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
