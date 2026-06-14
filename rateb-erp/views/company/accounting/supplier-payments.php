<?php
$bulkEnabled = $bulkEnabled ?? false;
$canPost = $canPost ?? false;
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h5>
    <a href="<?php echo rateb_app_url('accounting/accounts-payable'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> <?php echo __('accounts_payable'); ?>
    </a>
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
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
            <thead>
            <tr>
                <?php if ($bulkEnabled) { ?>
                <th class="rateb-bulk-th"><input type="checkbox" class="form-check-input" data-rateb-select-all></th>
                <?php } ?>
                <th><?php echo __('payment_no'); ?></th>
                <th><?php echo __('evaluation_date'); ?></th>
                <th><?php echo __('supplier'); ?></th>
                <th><?php echo __('purchase_order'); ?></th>
                <th class="text-end"><?php echo __('amount'); ?></th>
                <th><?php echo __('status'); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($items)) { ?>
            <tr><td colspan="<?php echo $bulkEnabled ? 8 : 7; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($items as $row) {
                $st = (string) ($row['status'] ?? '');
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
                <td class="text-end"><?php echo number_format((float) ($row['amount'] ?? 0), 2); ?></td>
                <td><span class="badge bg-<?php echo $st === 'posted' ? 'success' : 'secondary'; ?>"><?php echo __($st); ?></span></td>
                <td class="text-nowrap">
                    <?php if (!empty($row['journal_entry_id'])) { ?>
                    <a href="<?php echo rateb_app_url('journal-entries/' . (int) $row['journal_entry_id']); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-book"></i></a>
                    <?php } ?>
                    <?php if ($canPost && $st === 'posted') { ?>
                    <form method="post" action="<?php echo rateb_app_url('accounting/supplier-payments/' . (int) $row['id'] . '/void'); ?>" class="d-inline"
                          onsubmit="return confirm('<?php echo __('bulk_confirm_void'); ?>');">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i></button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
