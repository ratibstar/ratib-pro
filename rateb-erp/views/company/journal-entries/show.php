<?php
$desc = rateb_locale() === 'ar' && !empty($entry['description_ar']) ? $entry['description_ar'] : $entry['description'];
$status = (string) ($entry['status'] ?? '');
$sourceType = (string) ($entry['source_type'] ?? '');
$acctSvc = new \Rateb\App\Services\AccountingService();
$submitted = $acctSvc->isSubmittedForApproval($entry);
$displayStatus = $acctSvc->accountingRowDisplayStatus($entry);
$oversightOnly = rateb_accounting_final_post_oversight_only();
$sourceId = (int) ($entry['source_id'] ?? 0);
$sourceUrl = null;
$sourceLabel = '';
if ($sourceId > 0) {
    switch ($sourceType) {
        case 'cash_voucher':
            $sourceUrl = rateb_app_url('cash-vouchers/' . $sourceId);
            $sourceLabel = __('cash_vouchers');
            break;
        case 'purchase_order':
            $sourceUrl = rateb_app_url('purchase-orders/' . $sourceId);
            $sourceLabel = __('purchase_orders');
            break;
        case 'supplier_payment':
            $sourceUrl = rateb_app_url('accounting/supplier-payments');
            $sourceLabel = __('supplier_payments');
            break;
        case 'invoice':
            $sourceLabel = __('invoices');
            break;
    }
}
$badgeClass = $status === 'posted' ? 'success' : ($status === 'rejected' ? 'danger' : ($status === 'void' ? 'secondary' : ($displayStatus === 'awaiting_oversight_approval' ? 'info' : 'warning')));
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($entry['entry_no'] ?? ''); ?></span>
        <span class="badge bg-<?php echo $badgeClass; ?>">
            <?php echo __($displayStatus); ?>
        </span>
    </div>
    <div class="rateb-card-body">
        <p class="mb-1"><strong><?php echo __('evaluation_date'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($entry['entry_date'] ?? ''); ?></p>
        <p class="mb-1"><strong><?php echo __('source_type'); ?>:</strong> <?php echo __($sourceType); ?>
            <?php if ($sourceUrl) { ?>
            — <a href="<?php echo $sourceUrl; ?>"><?php echo Rateb\App\Core\View::escape($sourceLabel); ?> #<?php echo $sourceId; ?></a>
            <?php } elseif ($sourceId > 0 && $sourceLabel !== '') { ?>
            — <?php echo Rateb\App\Core\View::escape($sourceLabel); ?> #<?php echo $sourceId; ?>
            <?php } ?>
        </p>
        <?php if (!empty($entry['reject_reason'])) { ?>
        <p class="mb-1"><strong><?php echo __('reject_reason'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($entry['reject_reason']); ?></p>
        <?php } ?>
        <p class="mb-0"><?php echo Rateb\App\Core\View::escape($desc); ?></p>
    </div>
</div>
<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('code'); ?></th>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('cost_center'); ?></th>
                <th class="text-end"><?php echo __('debit'); ?></th>
                <th class="text-end"><?php echo __('credit'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $line) {
                $name = rateb_locale() === 'ar' && !empty($line['name_ar']) ? $line['name_ar'] : $line['name'];
                $ccName = '';
                if (!empty($line['cc_code'])) {
                    $ccName = $line['cc_code'] . ' — ' . (rateb_locale() === 'ar' && !empty($line['cc_name_ar']) ? $line['cc_name_ar'] : ($line['cc_name'] ?? ''));
                }
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($line['code']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                <td><?php echo $ccName !== '' ? Rateb\App\Core\View::escape($ccName) : '—'; ?></td>
                <td class="text-end"><?php echo number_format((float) $line['debit'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float) $line['credit'], 2); ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<div class="d-flex flex-wrap gap-2 mt-3">
    <a href="<?php echo rateb_app_url('journal-entries'); ?>" class="btn btn-outline-secondary"><i class="fas fa-list"></i> <?php echo __('journal_entries'); ?></a>
    <?php if (rateb_is_super_admin() && $status === 'draft' && $sourceType === 'manual') { ?>
    <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>" class="btn btn-outline-warning"><i class="fas fa-check-double"></i> <?php echo __('approvals_oversight'); ?></a>
    <?php } ?>
    <?php if (($canManage ?? false) && $status === 'draft' && $sourceType === 'manual') { ?>
    <a href="<?php echo rateb_app_url('journal-entries/' . (int) $entry['id'] . '/edit'); ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i> <?php echo __('edit'); ?></a>
    <?php if (($canManage ?? false) && $status === 'draft' && $sourceType === 'manual' && !$submitted) { ?>
    <form method="post" action="<?php echo rateb_app_url('journal-entries/' . (int) $entry['id'] . '/submit-approval'); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo Rateb\App\Core\View::escape(__('confirm_submit_for_approval')); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <input type="hidden" name="redirect_to" value="<?php echo Rateb\App\Core\View::escape(rateb_app_url('journal-entries/' . (int) $entry['id'])); ?>">
        <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> <?php echo __('submit_for_approval'); ?></button>
    </form>
    <?php } elseif (($canManage ?? false) && $status === 'draft' && $sourceType === 'manual' && $submitted) { ?>
    <span class="badge bg-warning text-dark align-self-center"><?php echo __('awaiting_oversight_approval'); ?></span>
    <?php } ?>
    <?php } ?>
    <?php if (($canApprove ?? false) && $status === 'posted' && $sourceType === 'manual') { ?>
    <form method="post" action="<?php echo rateb_app_url('journal-entries/' . (int) $entry['id'] . '/void'); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo __('bulk_confirm_undo'); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> <?php echo __('undo'); ?></button>
    </form>
    <?php } ?>
</div>
