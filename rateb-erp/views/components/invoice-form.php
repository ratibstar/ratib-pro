<?php
/** @var array<string, mixed>|null $item */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var array<int, array<string, mixed>> $companies */
/** @var array<int, array<string, mixed>> $subscriptions */
$isEdit = is_array($item) && (int) ($item['id'] ?? 0) > 0;
$invoiceId = $isEdit ? (int) $item['id'] : 0;
$action = $isEdit ? rateb_url($routePrefix . '/' . $invoiceId) : rateb_url($routePrefix);
$companies = $companies ?? [];
$subscriptions = $subscriptions ?? [];
$currency = (string) ($item['currency'] ?? 'SAR');
$currencyLabel = $currency === 'SAR' ? __('currency_sar') : $currency;
$taxRate = (string) ($item['tax_rate'] ?? '15');
$discountType = (string) ($item['discount_type'] ?? 'value');
$discountAmount = (string) ($item['discount_amount'] ?? '0');
$paymentTerms = (int) ($item['payment_terms_days'] ?? 30);
$paymentStatus = (string) ($item['payment_status'] ?? 'unpaid');
$invoiceType = (string) ($item['invoice_type'] ?? 'tax');
$paymentMethod = (string) ($item['payment_method'] ?? 'bank_transfer');
$amount = (float) ($item['amount'] ?? 0);
$taxAmount = (float) ($item['tax_amount'] ?? 0);
$totalAmount = (float) ($item['total_amount'] ?? 0);
$discountComputed = $discountType === 'percent'
    ? min($amount, $amount * ((float) $discountAmount / 100))
    : min($amount, (float) $discountAmount);
$subtotalBeforeTax = max(0, $amount - $discountComputed);
$documents = [];
$lineItems = $lineItems ?? [];
$bankAccounts = $bankAccounts ?? [];
$chartAccounts = $chartAccounts ?? [];
$selectedBankId = (int) ($item['supplier_bank_account_id'] ?? 0);
$supplierAccountNo = (string) ($item['supplier_account_no'] ?? '');
$buyerLegalName = (string) ($item['buyer_legal_name'] ?? '');
$buyerVat = (string) ($item['buyer_vat_number'] ?? '');
$buyerCr = (string) ($item['buyer_cr_number'] ?? '');
$buyerAddress = (string) ($item['buyer_address'] ?? '');
$maxAttachments = 5;
if ($isEdit && $invoiceId > 0) {
    $companyId = (int) ($item['company_id'] ?? 0);
    if ($companyId > 0) {
        $documents = (new \Rateb\App\Services\DocumentService())->listForEntity('invoice', $invoiceId, $companyId);
    }
}
$subJson = json_encode(array_map(static function (array $sub): array {
    return [
        'id' => (int) ($sub['id'] ?? 0),
        'company_id' => (int) ($sub['company_id'] ?? 0),
        'label' => (string) ($sub['label'] ?? ''),
        'amount' => (string) ($sub['amount'] ?? ''),
    ];
}, $subscriptions), JSON_UNESCAPED_UNICODE);
?>
<link href="<?php echo rateb_asset('css/invoice-form.css'); ?>" rel="stylesheet">
<div class="rateb-card rateb-invoice-form-card">
    <div class="rateb-card-header d-flex align-items-center gap-2">
        <i class="fas fa-file-invoice"></i>
        <?php echo Rateb\App\Core\View::escape($title ?? __('invoice_form_title')); ?>
    </div>
    <div class="rateb-card-body">
        <?php if ($companies === []) { ?>
        <div class="alert alert-warning"><?php echo __('billing_no_companies'); ?></div>
        <?php } ?>
        <form method="post" action="<?php echo $action; ?>" enctype="multipart/form-data"
              data-invoice-form="1"
              data-invoice-id="<?php echo $invoiceId; ?>"
              data-subscriptions="<?php echo Rateb\App\Core\View::escape($subJson ?: '[]'); ?>"
              data-subscription-lookup="<?php echo Rateb\App\Core\View::escape(rateb_url($routePrefix . '/subscription-lookup')); ?>"
              data-tax-profile-lookup="<?php echo Rateb\App\Core\View::escape(rateb_url($routePrefix . '/tax-profile-lookup')); ?>"
              data-chart-accounts-lookup="<?php echo Rateb\App\Core\View::escape(rateb_url($routePrefix . '/chart-accounts-lookup')); ?>"
              data-preview-url="<?php echo $isEdit ? Rateb\App\Core\View::escape(rateb_url($routePrefix . '/' . $invoiceId . '/preview')) : ''; ?>"
              data-preview-draft-url="<?php echo Rateb\App\Core\View::escape(rateb_url($routePrefix . '/preview-draft')); ?>"
              data-max-attachments="<?php echo $maxAttachments; ?>"
              data-preview-title="<?php echo Rateb\App\Core\View::escape(__('invoice_preview')); ?>"
              data-optional-label="<?php echo Rateb\App\Core\View::escape(__('optional')); ?>"
              data-after-days-label="<?php echo Rateb\App\Core\View::escape(__('due_after_days', ['days' => ':days'])); ?>"
              data-attachment-count-label="<?php echo Rateb\App\Core\View::escape(__('attachments_count', ['count' => ':count', 'max' => ':max'])); ?>"
              data-currency-label="<?php echo Rateb\App\Core\View::escape($currencyLabel); ?>"
              data-tax-lines-title="<?php echo Rateb\App\Core\View::escape(__('tax_invoice_lines_section')); ?>"
              data-lines-title="<?php echo Rateb\App\Core\View::escape(__('invoice_lines_section')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <input type="hidden" name="submit_action" value="draft" data-submit-action>

            <section class="rateb-invoice-section mb-4">
                <h6 class="rateb-invoice-section-title"><i class="fas fa-circle-info"></i> <?php echo __('invoice_info_section'); ?></h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_invoice_type"><?php echo __('invoice_type'); ?> <span class="text-danger">*</span></label>
                        <select class="form-select rateb-form-control" id="f_invoice_type" name="invoice_type" required>
                            <option value="tax"<?php echo $invoiceType === 'tax' ? ' selected' : ''; ?>><?php echo __('invoice_type_tax'); ?></option>
                            <option value="simplified"<?php echo $invoiceType === 'simplified' ? ' selected' : ''; ?>><?php echo __('invoice_type_simplified'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_invoice_no"><?php echo __('invoice_no'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input class="form-control rateb-form-control" type="text" id="f_invoice_no" name="invoice_no"
                                   value="<?php echo Rateb\App\Core\View::escape((string) ($item['invoice_no'] ?? '')); ?>" readonly>
                            <span class="input-group-text rateb-auto-tag"><?php echo __('automatic'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_company_id"><?php echo __('company'); ?> <span class="text-danger">*</span></label>
                        <select class="form-select rateb-form-control" id="f_company_id" name="company_id" required<?php echo $companies === [] ? ' disabled' : ''; ?>>
                            <option value=""><?php echo __('select_company'); ?></option>
                            <?php foreach ($companies as $company) { ?>
                            <option value="<?php echo (int) $company['id']; ?>"<?php echo (string) ($item['company_id'] ?? '') === (string) $company['id'] ? ' selected' : ''; ?>>
                                <?php echo Rateb\App\Core\View::escape($company['name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_subscription_id"><?php echo __('subscriptions'); ?> <span class="text-danger">*</span></label>
                        <select class="form-select rateb-form-control" id="f_subscription_id" name="subscription_id" required>
                            <option value=""><?php echo __('select_subscription'); ?></option>
                            <?php foreach ($subscriptions as $sub) { ?>
                            <option value="<?php echo (int) $sub['id']; ?>"
                                    data-amount="<?php echo Rateb\App\Core\View::escape((string) ($sub['amount'] ?? '')); ?>"
                                    <?php echo (string) ($item['subscription_id'] ?? '') === (string) $sub['id'] ? ' selected' : ''; ?>>
                                <?php echo Rateb\App\Core\View::escape($sub['label'] ?? ('#' . $sub['id'])); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_currency"><?php echo __('currency'); ?> <span class="text-danger">*</span></label>
                        <select class="form-select rateb-form-control" id="f_currency" name="currency" required>
                            <option value="SAR"<?php echo $currency === 'SAR' ? ' selected' : ''; ?>><?php echo __('currency_sar'); ?></option>
                            <option value="USD"<?php echo $currency === 'USD' ? ' selected' : ''; ?>><?php echo __('currency_usd'); ?></option>
                            <option value="EUR"<?php echo $currency === 'EUR' ? ' selected' : ''; ?>><?php echo __('currency_eur'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_po_number"><?php echo __('po_number'); ?></label>
                        <input class="form-control rateb-form-control" type="text" id="f_po_number" name="po_number"
                               value="<?php echo Rateb\App\Core\View::escape((string) ($item['po_number'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_payment_method"><?php echo __('payment_method'); ?> <span class="text-danger">*</span></label>
                        <select class="form-select rateb-form-control" id="f_payment_method" name="payment_method" required>
                            <option value="bank_transfer"<?php echo $paymentMethod === 'bank_transfer' ? ' selected' : ''; ?>><?php echo __('payment_method_bank'); ?></option>
                            <option value="card"<?php echo $paymentMethod === 'card' ? ' selected' : ''; ?>><?php echo __('payment_method_card'); ?></option>
                            <option value="cash"<?php echo $paymentMethod === 'cash' ? ' selected' : ''; ?>><?php echo __('payment_method_cash'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_payment_terms_days"><?php echo __('payment_terms'); ?> <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                            <input class="form-control rateb-form-control" type="number" min="0" max="365" id="f_payment_terms_days"
                                   name="payment_terms_days" value="<?php echo $paymentTerms; ?>" required>
                            <span class="input-group-text"><?php echo __('days'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_issued_at"><?php echo __('issued_at'); ?> <span class="text-danger">*</span></label>
                        <input class="form-control rateb-form-control" type="date" id="f_issued_at" name="issued_at"
                               value="<?php echo Rateb\App\Core\View::escape((string) ($item['issued_at'] ?? date('Y-m-d'))); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_due_date"><?php echo __('due_date'); ?> <span class="text-danger">*</span></label>
                        <input class="form-control rateb-form-control" type="date" id="f_due_date" name="due_date"
                               value="<?php echo Rateb\App\Core\View::escape((string) ($item['due_date'] ?? '')); ?>" required>
                        <small class="text-muted" data-due-hint><?php echo $paymentTerms > 0 ? __('due_after_days', ['days' => (string) $paymentTerms]) : ''; ?></small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_supplier_bank_account"><?php echo __('supplier_account_no'); ?></label>
                        <select class="form-select rateb-form-control" id="f_supplier_bank_account" name="supplier_bank_account_id" data-supplier-bank-select>
                            <option value=""><?php echo __('select'); ?>…</option>
                            <?php foreach ($bankAccounts as $bank) {
                                $bid = (int) ($bank['id'] ?? 0);
                                $label = trim((string) ($bank['bank_name'] ?? '') . ' — ' . (string) ($bank['account_number'] ?? ''));
                                if ($label === '—') {
                                    $label = (string) ($bank['name'] ?? ('#' . $bid));
                                }
                                ?>
                            <option value="<?php echo $bid; ?>"
                                    data-account-no="<?php echo Rateb\App\Core\View::escape((string) ($bank['account_number'] ?? '')); ?>"
                                <?php echo $bid === $selectedBankId ? ' selected' : ''; ?>>
                                <?php echo Rateb\App\Core\View::escape($label); ?>
                            </option>
                            <?php } ?>
                        </select>
                        <input class="form-control rateb-form-control rateb-ltr-num mt-1" type="text" id="f_supplier_account_no"
                               name="supplier_account_no" value="<?php echo Rateb\App\Core\View::escape($supplierAccountNo); ?>"
                               placeholder="<?php echo __('supplier_account_no'); ?>" data-supplier-account-no>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label"><?php echo __('payment_status'); ?></label>
                        <div>
                            <span class="badge bg-<?php echo $paymentStatus === 'paid' ? 'success' : ($paymentStatus === 'partial' ? 'warning' : 'secondary'); ?> rateb-payment-status-badge">
                                <?php echo __('payment_status_' . $paymentStatus); ?>
                            </span>
                        </div>
                        <input type="hidden" name="payment_status" value="<?php echo Rateb\App\Core\View::escape($paymentStatus); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_status"><?php echo __('status'); ?> <span class="text-danger">*</span></label>
                        <select class="form-select rateb-form-control" id="f_status" name="status" required>
                            <?php foreach (['draft', 'sent', 'paid', 'overdue', 'cancelled'] as $st) { ?>
                            <option value="<?php echo $st; ?>"<?php echo (string) ($item['status'] ?? 'draft') === $st ? ' selected' : ''; ?>><?php echo __($st); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label rateb-form-label" for="f_notes"><?php echo __('invoice_notes'); ?></label>
                        <textarea class="form-control rateb-form-control" id="f_notes" name="notes" rows="2"><?php echo Rateb\App\Core\View::escape((string) ($item['notes'] ?? '')); ?></textarea>
                    </div>
                </div>
            </section>

            <section class="rateb-invoice-section mb-4<?php echo $invoiceType === 'tax' ? '' : ' d-none'; ?>" data-tax-invoice-panel>
                <h6 class="rateb-invoice-section-title"><i class="fas fa-file-invoice-dollar"></i> <?php echo __('tax_invoice_section'); ?></h6>
                <p class="text-muted small mb-3"><?php echo __('tax_invoice_buyer_hint'); ?></p>
                <h6 class="rateb-invoice-subsection-title"><?php echo __('tax_invoice_buyer_section'); ?></h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_buyer_legal_name"><?php echo __('buyer_legal_name'); ?></label>
                        <input class="form-control rateb-form-control" type="text" id="f_buyer_legal_name"
                               name="buyer_legal_name" value="<?php echo Rateb\App\Core\View::escape($buyerLegalName); ?>"
                               data-tax-buyer-name>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_buyer_vat"><?php echo __('vat_number'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num" type="text" id="f_buyer_vat"
                               name="buyer_vat_number" value="<?php echo Rateb\App\Core\View::escape($buyerVat); ?>"
                               maxlength="15" data-tax-buyer-vat>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_buyer_cr"><?php echo __('cr_number'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num" type="text" id="f_buyer_cr"
                               name="buyer_cr_number" value="<?php echo Rateb\App\Core\View::escape($buyerCr); ?>"
                               data-tax-buyer-cr>
                    </div>
                    <div class="col-12">
                        <label class="form-label rateb-form-label" for="f_buyer_address"><?php echo __('buyer_address'); ?></label>
                        <input class="form-control rateb-form-control" type="text" id="f_buyer_address"
                               name="buyer_address" value="<?php echo Rateb\App\Core\View::escape($buyerAddress); ?>"
                               data-tax-buyer-address>
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-0 small d-none" data-tax-buyer-warning>
                    <?php echo __('tax_invoice_buyer_incomplete'); ?>
                </div>
            </section>

            <section class="rateb-invoice-section mb-4">
            <?php Rateb\App\Core\View::partial('invoice-lines', [
                'lineItems' => $lineItems,
                'defaultVat15' => true,
                'sectionTitle' => __('tax_invoice_lines_section'),
                'chartAccounts' => $chartAccounts,
                'showAccountColumn' => true,
            ]); ?>
            </section>

            <section class="rateb-invoice-section mb-4">
                <h6 class="rateb-invoice-section-title"><i class="fas fa-calculator"></i> <?php echo __('amount_details_section'); ?></h6>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_amount"><?php echo __('amount'); ?> <span class="text-danger">*</span></label>
                        <input class="form-control rateb-form-control" type="number" step="0.01" min="0.01" id="f_amount" name="amount"
                               value="<?php echo Rateb\App\Core\View::escape((string) ($item['amount'] ?? '')); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label"><?php echo __('discount'); ?></label>
                        <input type="hidden" name="discount_type" value="<?php echo Rateb\App\Core\View::escape($discountType); ?>">
                        <div class="btn-group w-100 mb-2" role="group">
                            <button type="button" class="btn btn-sm<?php echo $discountType === 'value' ? ' btn-primary' : ' btn-outline-secondary'; ?>" data-discount-mode="value"><?php echo __('discount_value'); ?></button>
                            <button type="button" class="btn btn-sm<?php echo $discountType === 'percent' ? ' btn-primary' : ' btn-outline-secondary'; ?>" data-discount-mode="percent"><?php echo __('discount_percent'); ?></button>
                        </div>
                        <div class="input-group">
                            <input class="form-control rateb-form-control" type="number" step="0.01" min="0" name="discount_amount"
                                   value="<?php echo Rateb\App\Core\View::escape($discountAmount); ?>">
                            <span class="input-group-text" data-discount-suffix><?php echo $discountType === 'percent' ? '%' : $currencyLabel; ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="f_tax_rate"><?php echo __('tax_rate_percent'); ?> <span class="text-danger">*</span></label>
                        <input class="form-control rateb-form-control" type="number" step="0.01" min="0" max="100" id="f_tax_rate" name="tax_rate"
                               value="<?php echo Rateb\App\Core\View::escape($taxRate); ?>" required>
                    </div>
                </div>
                <input type="hidden" name="tax_amount" value="<?php echo Rateb\App\Core\View::escape((string) $taxAmount); ?>">
                <input type="hidden" name="total_amount" value="<?php echo Rateb\App\Core\View::escape((string) $totalAmount); ?>">
                <div class="row g-3 mt-2 rateb-invoice-summary">
                    <div class="col-md-3 col-6">
                        <div class="rateb-summary-card">
                            <div class="rateb-summary-label"><?php echo __('subtotal_before_tax'); ?></div>
                            <div class="rateb-summary-value"><span data-summary-subtotal><?php echo number_format($amount, 2); ?></span> <?php echo Rateb\App\Core\View::escape($currency); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="rateb-summary-card">
                            <div class="rateb-summary-label"><?php echo __('discount'); ?></div>
                            <div class="rateb-summary-value text-warning"><span data-summary-discount><?php echo number_format($discountComputed, 2); ?></span> <?php echo Rateb\App\Core\View::escape($currency); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="rateb-summary-card">
                            <div class="rateb-summary-label"><?php echo __('total_tax'); ?> (<span data-summary-tax-label><?php echo Rateb\App\Core\View::escape($taxRate); ?>%</span>)</div>
                            <div class="rateb-summary-value"><span data-summary-tax><?php echo number_format($taxAmount, 2); ?></span> <?php echo Rateb\App\Core\View::escape($currency); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="rateb-summary-card rateb-summary-total">
                            <div class="rateb-summary-label"><?php echo __('total_after_tax'); ?></div>
                            <div class="rateb-summary-value"><span data-summary-total><?php echo number_format($totalAmount, 2); ?></span> <?php echo Rateb\App\Core\View::escape($currency); ?></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rateb-invoice-section mb-4">
                <h6 class="rateb-invoice-section-title"><i class="fas fa-paperclip"></i> <?php echo __('attachments'); ?></h6>
                <?php if ($documents !== []) { ?>
                <div class="rateb-invoice-attached-list mb-3" data-attached-list>
                    <?php foreach ($documents as $doc) {
                        $docId = (int) ($doc['id'] ?? 0);
                    ?>
                    <div class="rateb-invoice-attached-item" data-attached-item>
                        <span><i class="fas fa-file"></i> <?php echo Rateb\App\Core\View::escape($doc['file_name'] ?? ''); ?></span>
                        <div class="d-flex gap-1">
                            <?php
                            $docMime = (string) ($doc['mime_type'] ?? '');
                            $canInline = str_starts_with($docMime, 'image/') || $docMime === 'application/pdf';
                            if ($canInline) { ?>
                            <a href="<?php echo rateb_url('documents/view/' . $docId); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"><?php echo __('view_file'); ?></a>
                            <?php } ?>
                            <a href="<?php echo rateb_url('documents/download/' . $docId); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('download_file'); ?></a>
                            <?php if ($isEdit) { ?>
                            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $invoiceId . '/documents/' . $docId . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete_file')); ?>">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo __('delete_file'); ?></button>
                            </form>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
                <div class="rateb-invoice-dropzone" data-invoice-dropzone>
                    <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                    <p class="mb-2"><?php echo __('invoice_drop_hint'); ?></p>
                    <input class="form-control rateb-form-control d-none" type="file" name="entity_attachment[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" data-invoice-file-input>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-invoice-pick-files><?php echo __('choose_files'); ?></button>
                    <div class="small text-muted mt-2" data-attachment-meta>
                        <?php echo __('attachments_count', ['count' => (string) count($documents), 'max' => (string) $maxAttachments]); ?>
                    </div>
                    <div class="rateb-invoice-pending-files mt-2" data-pending-files></div>
                    <small class="text-muted d-block mt-2"><?php echo __('attachment_hint'); ?></small>
                </div>
            </section>

            <div class="rateb-invoice-print-area d-none" data-invoice-print-area>
                <h4><?php echo __('invoice_preview'); ?></h4>
                <p><strong><?php echo __('invoice_no'); ?>:</strong> <span data-print-invoice-no><?php echo Rateb\App\Core\View::escape((string) ($item['invoice_no'] ?? '')); ?></span></p>
                <p><strong><?php echo __('issued_at'); ?>:</strong> <span data-print-issued><?php echo Rateb\App\Core\View::formatDate((string) ($item['issued_at'] ?? '')); ?></span></p>
                <p><strong><?php echo __('due_date'); ?>:</strong> <span data-print-due><?php echo Rateb\App\Core\View::formatDate((string) ($item['due_date'] ?? '')); ?></span></p>
                <p><strong><?php echo __('total_after_tax'); ?>:</strong> <span data-print-total><?php echo number_format($totalAmount, 2); ?></span> <?php echo Rateb\App\Core\View::escape($currency); ?></p>
            </div>

            <div class="rateb-invoice-actions d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
                <div class="d-flex gap-2">
                    <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
                    <button type="submit" class="btn btn-outline-primary" data-set-action="draft"<?php echo $companies === [] ? ' disabled' : ''; ?>>
                        <?php echo __('save_as_draft'); ?>
                    </button>
                </div>
                <button type="submit" class="btn btn-primary" data-set-action="send"<?php echo $companies === [] ? ' disabled' : ''; ?>>
                    <i class="fas fa-paper-plane"></i> <?php echo __('save_and_send'); ?>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-invoice-preview>
                    <i class="fas fa-eye"></i> <?php echo __('pdf_preview'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php if ($isEdit) {
    $docBarcode = (new \Rateb\App\Services\DocumentBarcodeService())->labelData('invoice', $invoiceId);
    Rateb\App\Core\View::partial('document-barcode-label', ['docBarcode' => $docBarcode]);
} ?>
<script src="<?php echo rateb_asset('js/billing-invoice.js'); ?>" defer></script>
<script>
(function () {
    document.querySelectorAll('[data-set-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.querySelector('[data-submit-action]');
            if (input) input.value = btn.getAttribute('data-set-action') || 'draft';
        });
    });

    function parseInvoiceNum(val) {
        var n = parseFloat(val);
        return isNaN(n) ? 0 : n;
    }

    function fmtInvoiceNum(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function fallbackInvoiceRecalc(form) {
        if (!form || form.dataset.invoiceBound === '1') {
            return;
        }
        var amountEl = form.querySelector('[name="amount"]');
        var taxRateEl = form.querySelector('[name="tax_rate"]');
        var discountEl = form.querySelector('[name="discount_amount"]');
        var discountTypeEl = form.querySelector('[name="discount_type"]');
        var taxAmountEl = form.querySelector('[name="tax_amount"]');
        var totalEl = form.querySelector('[name="total_amount"]');
        if (!amountEl || !taxRateEl) {
            return;
        }
        function recalc() {
            var amount = Math.max(0, parseInvoiceNum(amountEl.value));
            var taxRate = Math.max(0, parseInvoiceNum(taxRateEl.value));
            var discVal = Math.max(0, parseInvoiceNum(discountEl && discountEl.value));
            var discType = (discountTypeEl && discountTypeEl.value) || 'value';
            var discount = discType === 'percent' ? Math.min(amount, amount * (discVal / 100)) : Math.min(amount, discVal);
            var subtotal = Math.max(0, amount - discount);
            var tax = Math.round(subtotal * (taxRate / 100) * 100) / 100;
            var total = Math.round((subtotal + tax) * 100) / 100;
            if (taxAmountEl) taxAmountEl.value = fmtInvoiceNum(tax);
            if (totalEl) totalEl.value = fmtInvoiceNum(total);
            var setText = function (sel, val) {
                var el = form.querySelector(sel);
                if (el) el.textContent = fmtInvoiceNum(val);
            };
            setText('[data-summary-subtotal]', amount);
            setText('[data-summary-discount]', discount);
            setText('[data-summary-tax]', tax);
            setText('[data-summary-total]', total);
            var taxLabel = form.querySelector('[data-summary-tax-label]');
            if (taxLabel) taxLabel.textContent = taxRate + '%';
        }
        [amountEl, taxRateEl, discountEl].forEach(function (el) {
            if (el) el.addEventListener('input', recalc);
        });
        form.querySelectorAll('[data-discount-mode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (discountTypeEl) discountTypeEl.value = btn.getAttribute('data-discount-mode') || 'value';
                recalc();
            });
        });
        recalc();
    }

    function bootInvoiceFormUi() {
        if (typeof window.ratebInitInvoiceForm === 'function') {
            document.querySelectorAll('[data-invoice-form]').forEach(window.ratebInitInvoiceForm);
            return;
        }
        document.querySelectorAll('[data-invoice-form]').forEach(fallbackInvoiceRecalc);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootInvoiceFormUi);
    } else {
        bootInvoiceFormUi();
    }
})();
</script>
