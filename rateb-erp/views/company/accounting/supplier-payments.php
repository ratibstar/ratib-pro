<?php
$bulkEnabled = $bulkEnabled ?? false;
$canPost = $canPost ?? false;
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h5 class="mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h5>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if (!empty($exportRoute)) {
            Rateb\App\Core\View::partial('export-toolbar', [
                'exportRoute' => $exportRoute,
                'exportEnabled' => $exportEnabled ?? true,
                'inline' => true,
            ]);
        } ?>
        <?php if ($canPost) { ?>
        <a href="<?php echo rateb_app_url('accounting/supplier-payments/create'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('create_supplier_payment'); ?>
        </a>
        <?php } ?>
        <a href="<?php echo rateb_app_url('accounting/accounts-payable'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-list"></i> <?php echo __('accounts_payable'); ?>
        </a>
    </div>
</div>
<?php if (!$canPost) {
    Rateb\App\Core\View::partial('accounting-permissions-note', ['permKey' => 'supplier_payment']);
} ?>
<div class="rateb-card">
    <?php if ($bulkEnabled && !empty($items)) { ?>
    <div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
        <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0</span>
        <form method="post" action="<?php echo rateb_app_url('accounting/supplier-payments/bulk-void'); ?>" class="d-inline" data-rateb-bulk-form="void"
              data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_void')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-ban"></i> <?php echo __('bulk_void'); ?></button>
        </form>
    </div>
    <?php } ?>
    <div class="rateb-card-body p-0 table-responsive">
        <table class="table rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
            <thead>
            <tr>
                <?php if ($bulkEnabled) { ?>
                <th class="rateb-bulk-th"><input type="checkbox" class="form-check-input" data-rateb-select-all></th>
                <?php } ?>
                <th><?php echo __('payment_no'); ?></th>
                <th><?php echo __('actual_payment_date'); ?></th>
                <th><?php echo __('due_date'); ?></th>
                <th><?php echo __('supplier'); ?></th>
                <th><?php echo __('purchase_order'); ?></th>
                <th><?php echo __('supplier_invoice'); ?></th>
                <th><?php echo __('payment_method'); ?></th>
                <th><?php echo __('reference_bank_or_check'); ?></th>
                <th class="text-end"><?php echo __('amount'); ?></th>
                <th><?php echo __('status'); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($items)) { ?>
            <tr><td colspan="<?php echo $bulkEnabled ? 12 : 11; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($items as $row) {
                $st = (string) ($row['status'] ?? '');
                $method = (string) ($row['payment_method'] ?? '');
                $methodLabel = match ($method) {
                    'bank', 'bank_transfer' => __('payment_method_bank'),
                    'cheque' => __('payment_method_cheque'),
                    'cash' => __('payment_method_cash'),
                    default => $method,
                };
                $paymentId = (int) ($row['id'] ?? 0);
                $doc = $paymentId > 0 ? (new \Rateb\App\Services\DocumentService())->latestForEntity((int) ($row['company_id'] ?? 0), 'supplier_payment', $paymentId) : null;
                ?>
            <tr>
                <?php if ($bulkEnabled) { ?>
                <td class="rateb-bulk-td">
                    <?php if ($st === 'posted') { ?>
                    <input type="checkbox" class="form-check-input" data-rateb-row-check value="<?php echo (int) $row['id']; ?>">
                    <?php } ?>
                </td>
                <?php } ?>
                <td><?php echo Rateb\App\Core\View::escape($row['payment_no'] ?? ''); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($row['payment_date'] ?? ''); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($row['due_date'] ?? '—'); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($row['supplier_name'] ?? '—'); ?></td>
                <td>
                    <?php if (!empty($row['purchase_order_id'])) { ?>
                    <a href="<?php echo rateb_app_url('purchase-orders/' . (int) $row['purchase_order_id']); ?>">
                        <?php echo Rateb\App\Core\View::escape($row['order_no'] ?? '—'); ?>
                    </a>
                    <?php } else { ?>
                    <?php echo Rateb\App\Core\View::escape($row['order_no'] ?? '—'); ?>
                    <?php } ?>
                </td>
                <td><?php echo Rateb\App\Core\View::escape($row['invoice_no'] ?? '—'); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($methodLabel); ?></td>
                <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($row['reference_no'] ?? '—'); ?></td>
                <td class="text-end rateb-ltr-num"><?php echo number_format((float) ($row['amount'] ?? 0), 2); ?></td>
                <td><span class="badge bg-<?php echo $st === 'posted' ? 'success' : 'secondary'; ?>"><?php echo __($st); ?></span></td>
                <td class="text-nowrap">
                    <?php if ($doc) {
                        $docId = (int) ($doc['id'] ?? 0);
                        $mime = (string) ($doc['mime_type'] ?? '');
                        if (str_starts_with($mime, 'image/') || $mime === 'application/pdf') { ?>
                    <a href="<?php echo rateb_url('documents/view/' . $docId); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" title="<?php echo __('view_file'); ?>"><i class="fas fa-paperclip"></i></a>
                    <?php } else { ?>
                    <a href="<?php echo rateb_url('documents/download/' . $docId); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo __('download_file'); ?>"><i class="fas fa-paperclip"></i></a>
                    <?php }
                    } ?>
                    <?php if (!empty($row['journal_entry_id'])) { ?>
                    <a href="<?php echo rateb_app_url('journal-entries/' . (int) $row['journal_entry_id']); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('journal_entry'); ?>"><i class="fas fa-book"></i></a>
                    <?php } ?>
                    <?php if ($canPost && $st === 'posted') { ?>
                    <form method="post" action="<?php echo rateb_app_url('accounting/supplier-payments/' . (int) $row['id'] . '/void'); ?>" class="d-inline"
                          onsubmit="return confirm('<?php echo __('bulk_confirm_void'); ?>');">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('void'); ?>"><i class="fas fa-ban"></i></button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
