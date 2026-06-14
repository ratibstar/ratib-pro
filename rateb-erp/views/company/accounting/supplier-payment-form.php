<?php
use Rateb\App\Services\FormLookupService;

$po = $po ?? null;
$due = $po ? max(0, (float) ($po['total_amount'] ?? 0) - (float) ($paidAmount ?? 0)) : 0;
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('supplier_payment'); ?></div>
    <div class="rateb-card-body">
        <?php if (!$po) { ?>
        <p class="text-muted mb-0"><?php echo __('supplier_payment_select_po'); ?></p>
        <a href="<?php echo rateb_app_url('accounting/accounts-payable'); ?>" class="btn btn-outline-secondary mt-3"><?php echo __('cancel'); ?></a>
        <?php } else {
            $formFields = FormLookupService::supplierPaymentFormFields($due);
            $lookups = (new FormLookupService())->forFields($formFields);
            $paymentItem = [
                'amount' => number_format($due, 2, '.', ''),
                'payment_date' => date('Y-m-d'),
            ];
            ?>
        <form method="post" action="<?php echo rateb_app_url('accounting/supplier-payments'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <input type="hidden" name="purchase_order_id" value="<?php echo (int) $po['id']; ?>">
            <input type="hidden" name="supplier_id" value="<?php echo (int) ($po['supplier_id'] ?? 0); ?>">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label rateb-form-label"><?php echo __('order_no'); ?></label>
                    <input type="text" class="form-control rateb-form-control" readonly value="<?php echo Rateb\App\Core\View::escape($po['order_no']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label rateb-form-label"><?php echo __('supplier'); ?></label>
                    <input type="text" class="form-control rateb-form-control" readonly value="<?php echo Rateb\App\Core\View::escape($po['supplier_name'] ?? '—'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label rateb-form-label"><?php echo __('amount_due'); ?></label>
                    <input type="text" class="form-control rateb-form-control" readonly value="<?php echo number_format($due, 2); ?> SAR">
                </div>
            </div>
            <?php Rateb\App\Core\View::partial('accounting-form', [
                'formFields' => $formFields,
                'item' => $paymentItem,
                'lookups' => $lookups,
            ]); ?>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <?php echo __('post_payment'); ?></button>
                <a href="<?php echo rateb_app_url('accounting/accounts-payable'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
        <?php } ?>
    </div>
</div>
