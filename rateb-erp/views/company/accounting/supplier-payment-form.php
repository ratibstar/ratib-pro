<?php
use Rateb\App\Services\FormLookupService;

$po = $po ?? null;
$payable = $payable ?? null;
$due = (float) ($payable['due'] ?? ($po ? max(0, (float) ($po['total_amount'] ?? 0) - (float) ($paidAmount ?? 0)) : 0));
$supplierBalance = (float) ($supplierBalance ?? 0);
$payableOrders = $payableOrders ?? [];
$payableInvoices = $payableInvoices ?? [];
$suppliers = $suppliers ?? [];
$selectedSupplierId = (int) ($selectedSupplierId ?? ($po['supplier_id'] ?? 0));
$selectedPoId = (int) ($selectedPoId ?? ($po['id'] ?? 0));
$selectedInvoiceId = (int) ($selectedInvoiceId ?? 0);
$linkType = (string) ($linkType ?? ($selectedInvoiceId > 0 ? 'invoice' : 'po'));
$payableJson = json_encode([
    'orders' => array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'supplier_id' => (int) ($row['supplier_id'] ?? 0),
            'order_no' => (string) ($row['order_no'] ?? ''),
            'due' => (float) ($row['due_amount'] ?? 0),
            'due_date' => (string) ($row['expected_date'] ?? $row['order_date'] ?? ''),
            'supplier_name' => (string) ($row['supplier_name'] ?? ''),
        ];
    }, $payableOrders),
    'invoices' => array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'purchase_order_id' => (int) ($row['purchase_order_id'] ?? 0),
            'supplier_id' => (int) ($row['supplier_id'] ?? 0),
            'invoice_no' => (string) ($row['invoice_no'] ?? ''),
            'po_number' => (string) ($row['po_number'] ?? ''),
            'due' => (float) ($row['total_amount'] ?? 0),
            'due_date' => (string) ($row['due_date'] ?? $row['issued_at'] ?? ''),
            'supplier_name' => (string) ($row['supplier_name'] ?? ''),
        ];
    }, $payableInvoices),
    'balances' => $supplierBalances ?? [],
], JSON_UNESCAPED_UNICODE);

Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<link href="<?php echo rateb_asset('css/supplier-payment.css'); ?>" rel="stylesheet">
<div class="rateb-card rateb-sp-form-card">
    <div class="rateb-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?php echo __('create_supplier_payment'); ?></span>
        <a href="<?php echo rateb_app_url('accounting/supplier-payments'); ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('supplier_payments'); ?>
        </a>
    </div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_app_url('accounting/supplier-payments'); ?>"
              enctype="multipart/form-data" class="rateb-sp-form" data-supplier-payment-form="1"
              data-payables="<?php echo Rateb\App\Core\View::escape($payableJson ?: '{}'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <input type="hidden" name="supplier_id" value="<?php echo $selectedSupplierId; ?>" data-sp-supplier-id>
            <input type="hidden" name="purchase_order_id" value="<?php echo $selectedPoId; ?>" data-sp-po-id>
            <input type="hidden" name="invoice_id" value="<?php echo $selectedInvoiceId; ?>" data-sp-invoice-id>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label rateb-form-label"><?php echo __('supplier'); ?></label>
                    <select class="form-select rateb-form-control" data-sp-supplier-select required>
                        <option value=""><?php echo __('select'); ?>…</option>
                        <?php foreach ($suppliers as $sup) {
                            $sid = (int) ($sup['id'] ?? 0); ?>
                        <option value="<?php echo $sid; ?>"<?php echo $sid === $selectedSupplierId ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($sup['name'] ?? ''); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <?php if (empty($suppliers)) { ?>
                    <p class="form-text text-warning small mb-0"><?php echo __('supplier_payment_no_suppliers'); ?></p>
                    <?php } ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label rateb-form-label"><?php echo __('supplier_balance_due'); ?></label>
                    <input type="text" class="form-control rateb-form-control rateb-ltr-num" readonly
                           value="<?php echo number_format($supplierBalance, 2); ?> SAR" data-sp-supplier-balance>
                </div>
                <div class="col-md-4">
                    <label class="form-label rateb-form-label"><?php echo __('amount_due'); ?> <small class="text-muted">(<?php echo __('link_document'); ?>)</small></label>
                    <input type="text" class="form-control rateb-form-control rateb-ltr-num" readonly
                           value="<?php echo number_format($due, 2); ?> SAR" data-sp-line-due>
                </div>
            </div>

            <div class="rateb-sp-link-panel mb-3">
                <label class="form-label rateb-form-label d-block"><?php echo __('link_type'); ?></label>
                <div class="btn-group btn-group-sm mb-3" role="group">
                    <input type="radio" class="btn-check" name="link_type_ui" id="sp-link-po" value="po"<?php echo $linkType !== 'invoice' ? ' checked' : ''; ?> data-sp-link-type>
                    <label class="btn btn-outline-primary" for="sp-link-po"><?php echo __('link_purchase_order'); ?></label>
                    <input type="radio" class="btn-check" name="link_type_ui" id="sp-link-inv" value="invoice"<?php echo $linkType === 'invoice' ? ' checked' : ''; ?> data-sp-link-type>
                    <label class="btn btn-outline-primary" for="sp-link-inv"><?php echo __('link_supplier_invoice'); ?></label>
                </div>
                <div data-sp-po-picker>
                    <label class="form-label rateb-form-label"><?php echo __('purchase_order'); ?></label>
                    <select class="form-select rateb-form-control" data-sp-po-select>
                        <option value=""><?php echo __('supplier_payment_select_po'); ?></option>
                        <?php foreach ($payableOrders as $row) {
                            $id = (int) ($row['id'] ?? 0); ?>
                        <option value="<?php echo $id; ?>"
                                data-supplier-id="<?php echo (int) ($row['supplier_id'] ?? 0); ?>"
                                data-due="<?php echo Rateb\App\Core\View::escape((string) ($row['due_amount'] ?? 0)); ?>"
                                data-due-date="<?php echo Rateb\App\Core\View::escape((string) ($row['expected_date'] ?? $row['order_date'] ?? '')); ?>"
                                data-label="<?php echo Rateb\App\Core\View::escape(($row['order_no'] ?? '') . ' — ' . ($row['supplier_name'] ?? '')); ?>"
                            <?php echo $id === $selectedPoId ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape(($row['order_no'] ?? '') . ' — ' . number_format((float) ($row['due_amount'] ?? 0), 2) . ' SAR'); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="d-none" data-sp-invoice-picker>
                    <label class="form-label rateb-form-label"><?php echo __('supplier_invoice'); ?></label>
                    <select class="form-select rateb-form-control" data-sp-invoice-select>
                        <option value=""><?php echo __('select'); ?>…</option>
                        <?php foreach ($payableInvoices as $row) {
                            $id = (int) ($row['id'] ?? 0); ?>
                        <option value="<?php echo $id; ?>"
                                data-po-id="<?php echo (int) ($row['purchase_order_id'] ?? 0); ?>"
                                data-supplier-id="<?php echo (int) ($row['supplier_id'] ?? 0); ?>"
                                data-due="<?php echo Rateb\App\Core\View::escape((string) ($row['total_amount'] ?? 0)); ?>"
                                data-due-date="<?php echo Rateb\App\Core\View::escape((string) ($row['due_date'] ?? $row['issued_at'] ?? '')); ?>"
                            <?php echo $id === $selectedInvoiceId ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape(($row['invoice_no'] ?? '') . ' / ' . ($row['po_number'] ?? '') . ' — ' . number_format((float) ($row['total_amount'] ?? 0), 2)); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <p class="form-text text-muted mb-0 mt-2"><?php echo __('partial_payment_hint'); ?></p>
                <?php if (empty($payableOrders) && empty($payableInvoices)) { ?>
                <div class="alert alert-warning mt-3 mb-0 small">
                    <?php echo __('supplier_payment_no_open_docs'); ?>
                    <a href="<?php echo rateb_app_url('accounting/accounts-payable'); ?>"><?php echo __('accounts_payable'); ?></a>
                </div>
                <?php } ?>
            </div>

            <?php
            $formFields = FormLookupService::supplierPaymentFormFields($due > 0 ? $due : 999999999);
            $lookups = (new FormLookupService())->forFields($formFields);
            $paymentItem = [
                'amount' => $due > 0 ? number_format($due, 2, '.', '') : '',
                'payment_date' => date('Y-m-d'),
                'due_date' => (string) ($payable['due_date'] ?? ($po['expected_date'] ?? $po['order_date'] ?? '')),
                'payment_method' => 'bank',
            ];
            ?>
            <div class="rateb-sp-fields">
                <?php Rateb\App\Core\View::partial('accounting-form', [
                    'formFields' => $formFields,
                    'item' => $paymentItem,
                    'lookups' => $lookups,
                ]); ?>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label rateb-form-label" for="sp_entity_attachment">
                            <i class="fas fa-paperclip"></i> <?php echo __('attach_transfer_voucher'); ?>
                        </label>
                        <input class="form-control rateb-form-control" type="file" id="sp_entity_attachment"
                               name="entity_attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                        <div class="form-text text-muted small"><?php echo __('attachment_hint'); ?></div>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-success" data-sp-submit>
                    <i class="fas fa-check"></i> <?php echo __('post_payment'); ?>
                </button>
                <a href="<?php echo rateb_app_url('accounting/supplier-payments'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
<script>
window.ratebSpLabels = <?php echo json_encode([
    'bank_reference' => __('bank_reference'),
    'check_number' => __('check_number'),
    'select_document' => __('supplier_payment_select_document'),
    'select_supplier' => __('supplier_payment_select_supplier'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo rateb_asset('js/supplier-payment.js'); ?>" defer></script>
