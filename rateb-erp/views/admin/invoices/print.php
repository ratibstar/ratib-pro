<?php
/** @var array<string, mixed> $item */
/** @var array<string, mixed>|null $company */
/** @var array<int, array<string, mixed>>|null $lines */
/** @var bool|null $draft */
$company = $company ?? [];
$lines = $lines ?? [];
$currency = (string) ($item['currency'] ?? 'SAR');
$invoiceType = (string) ($item['invoice_type'] ?? 'tax');
$invoiceTypeLabel = $invoiceType === 'simplified' ? __('invoice_type_simplified') : __('invoice_type_tax');
$buyerLegalName = trim((string) ($item['buyer_legal_name'] ?? ''));
$buyerVat = trim((string) ($item['buyer_vat_number'] ?? ''));
$buyerCr = trim((string) ($item['buyer_cr_number'] ?? ''));
$buyerAddress = trim((string) ($item['buyer_address'] ?? ''));
if ($invoiceType === 'tax' && $buyerLegalName === '' && !empty($item['company_id'])) {
    $buyerProfile = (new \Rateb\App\Services\ZatcaService())->getTaxProfile((int) $item['company_id']);
    $buyerLegalName = trim((string) ($buyerProfile['legal_name_ar'] ?? $buyerProfile['legal_name_en'] ?? ''));
    if ($buyerLegalName === '' && is_array($company)) {
        $buyerLegalName = (string) ($company['name'] ?? '');
    }
    $buyerVat = trim((string) ($buyerProfile['vat_number'] ?? ''));
    $buyerCr = trim((string) ($buyerProfile['cr_number'] ?? ''));
    $buyerAddress = implode('، ', array_filter([
        trim((string) ($buyerProfile['street'] ?? '')),
        trim((string) ($buyerProfile['building_no'] ?? '')),
        trim((string) ($buyerProfile['city'] ?? '')),
        trim((string) ($buyerProfile['postal_code'] ?? '')),
    ]));
}
$discountType = (string) ($item['discount_type'] ?? 'value');
$discountVal = (float) ($item['discount_amount'] ?? 0);
$amount = (float) ($item['amount'] ?? 0);
$discount = $discountType === 'percent' ? min($amount, $amount * ($discountVal / 100)) : min($amount, $discountVal);
$supplierAccountNo = trim((string) ($item['supplier_account_no'] ?? ''));
$supplierBankName = '';
if (!empty($item['supplier_bank_account_id'])) {
    $bankRow = (new \Rateb\App\Models\BankAccount())->find((int) $item['supplier_bank_account_id']);
    if ($bankRow) {
        $supplierBankName = (string) ($bankRow['bank_name'] ?? '');
        if ($supplierAccountNo === '') {
            $supplierAccountNo = trim((string) ($bankRow['account_number'] ?? ''));
        }
    }
}
$accountLabels = [];
foreach ($lines as $line) {
    $aid = (int) ($line['account_id'] ?? 0);
    if ($aid > 0 && !isset($accountLabels[$aid])) {
        $acc = (new \Rateb\App\Models\ChartOfAccount())->find($aid);
        if ($acc) {
            $name = rateb_locale() === 'ar' && !empty($acc['name_ar']) ? (string) $acc['name_ar'] : (string) ($acc['name'] ?? '');
            $accountLabels[$aid] = trim((string) ($acc['code'] ?? '') . ' — ' . $name);
        }
    }
}
$colSpan = 6;
?>
<div class="rateb-invoice-print">
    <div class="rateb-invoice-print-header d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
        <div>
            <h2 class="mb-1"><?php echo Rateb\App\Core\View::escape($invoiceTypeLabel); ?></h2>
            <div class="text-muted"><?php echo Rateb\App\Core\View::escape((string) ($company['name'] ?? '')); ?></div>
            <?php if ($invoiceType === 'tax' && $buyerVat !== '') { ?>
            <div class="small text-muted"><?php echo __('vat_number'); ?>: <?php echo Rateb\App\Core\View::escape($buyerVat); ?></div>
            <?php } ?>
            <?php if ($invoiceType === 'tax' && $buyerCr !== '') { ?>
            <div class="small text-muted"><?php echo __('cr_number'); ?>: <?php echo Rateb\App\Core\View::escape($buyerCr); ?></div>
            <?php } ?>
            <?php if ($invoiceType === 'tax' && $buyerLegalName !== '') { ?>
            <div class="small text-muted"><?php echo __('buyer_legal_name'); ?>: <?php echo Rateb\App\Core\View::escape($buyerLegalName); ?></div>
            <?php } ?>
            <?php if ($invoiceType === 'tax' && $buyerAddress !== '') { ?>
            <div class="small text-muted"><?php echo __('buyer_address'); ?>: <?php echo Rateb\App\Core\View::escape($buyerAddress); ?></div>
            <?php } ?>
            <?php if (!empty($company['email'])) { ?>
            <div class="small text-muted"><?php echo Rateb\App\Core\View::escape((string) $company['email']); ?></div>
            <?php } ?>
        </div>
        <div class="text-end">
            <div><strong><?php echo __('invoice_no'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($item['invoice_no'] ?? '')); ?></div>
            <div><strong><?php echo __('issued_at'); ?>:</strong> <?php echo Rateb\App\Core\View::formatDate((string) ($item['issued_at'] ?? '')); ?></div>
            <div><strong><?php echo __('due_date'); ?>:</strong> <?php echo Rateb\App\Core\View::formatDate((string) ($item['due_date'] ?? '')); ?></div>
            <div><strong><?php echo __('status'); ?>:</strong> <?php echo Rateb\App\Core\View::escape(__((string) ($item['status'] ?? 'draft'))); ?></div>
        </div>
    </div>

    <table class="table table-bordered rateb-po-print-table">
        <thead>
        <tr>
            <th>#</th>
            <th><?php echo __('description'); ?></th>
            <th class="text-end"><?php echo __('quantity'); ?></th>
            <th class="text-end"><?php echo __('unit_price'); ?></th>
            <th class="text-end"><?php echo __('tax_amount'); ?></th>
            <th><?php echo __('account'); ?></th>
            <th class="text-end"><?php echo __('line_total'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($lines !== []) {
            $n = 0;
            foreach ($lines as $line) {
                $n++;
                $qty = (float) ($line['quantity'] ?? 1);
                $price = (float) ($line['unit_price'] ?? 0);
                $taxRate = (float) ($line['tax_rate'] ?? 0);
                $excluding = !isset($line['excluding_tax']) || (int) $line['excluding_tax'] === 1;
                $totals = \Rateb\App\Helpers\LineItems::lineTotals($qty, $price, $taxRate, $excluding);
                $aid = (int) ($line['account_id'] ?? 0);
                ?>
        <tr>
            <td><?php echo $n; ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($line['item_name'] ?? '')); ?></td>
            <td class="text-end"><?php echo number_format($qty, 2); ?></td>
            <td class="text-end"><?php echo number_format($price, 2); ?></td>
            <td class="text-end"><?php echo number_format($totals['tax'], 2); ?></td>
            <td class="small"><?php echo Rateb\App\Core\View::escape($accountLabels[$aid] ?? '—'); ?></td>
            <td class="text-end"><?php echo number_format($totals['total'], 2); ?></td>
        </tr>
        <?php }
        } else { ?>
        <tr>
            <td>1</td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($item['notes'] ?? __('subscriptions'))); ?></td>
            <td class="text-end">1</td>
            <td class="text-end"><?php echo number_format($amount, 2); ?></td>
            <td class="text-end"><?php echo number_format((float) ($item['tax_amount'] ?? 0), 2); ?></td>
            <td>—</td>
            <td class="text-end"><?php echo number_format((float) ($item['total_amount'] ?? 0), 2); ?></td>
        </tr>
        <?php } ?>
        </tbody>
        <tfoot>
        <tr>
            <td colspan="<?php echo $colSpan; ?>" class="text-end"><?php echo __('subtotal_before_tax'); ?></td>
            <td class="text-end"><?php echo number_format($amount, 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></td>
        </tr>
        <?php if ($discount > 0) { ?>
        <tr>
            <td colspan="<?php echo $colSpan; ?>" class="text-end"><?php echo __('discount'); ?></td>
            <td class="text-end">-<?php echo number_format($discount, 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></td>
        </tr>
        <?php } ?>
        <tr>
            <td colspan="<?php echo $colSpan; ?>" class="text-end"><?php echo __('tax_amount'); ?></td>
            <td class="text-end"><?php echo number_format((float) ($item['tax_amount'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></td>
        </tr>
        <tr class="rateb-po-print-total">
            <td colspan="<?php echo $colSpan; ?>" class="text-end"><strong><?php echo __('total_after_tax'); ?></strong></td>
            <td class="text-end"><strong><?php echo number_format((float) ($item['total_amount'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></strong></td>
        </tr>
        </tfoot>
    </table>

    <?php if (!empty($item['notes'])) { ?>
    <p class="mt-3"><strong><?php echo __('invoice_notes'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) $item['notes']); ?></p>
    <?php } ?>
    <?php if ($supplierAccountNo !== '') { ?>
    <div class="rateb-invoice-print-footer border-top pt-3 mt-4">
        <p class="mb-1"><strong><?php echo __('invoice_supplier_bank_footer'); ?>:</strong></p>
        <p class="mb-0 rateb-ltr-num">
            <?php if ($supplierBankName !== '') { ?>
            <?php echo Rateb\App\Core\View::escape($supplierBankName); ?> —
            <?php } ?>
            <?php echo Rateb\App\Core\View::escape($supplierAccountNo); ?>
        </p>
    </div>
    <?php } ?>
    <?php if (!empty($draft)) { ?>
    <p class="text-muted small mt-3"><?php echo __('invoice_preview_draft_note'); ?></p>
    <?php } ?>
</div>
