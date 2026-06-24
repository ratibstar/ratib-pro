<?php
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
$desc = rateb_locale() === 'ar' && !empty($voucher['description_ar']) ? $voucher['description_ar'] : $voucher['description'];
$counterName = rateb_locale() === 'ar' && !empty($voucher['counter_name_ar']) ? $voucher['counter_name_ar'] : $voucher['counter_name'];
$st = (string) ($voucher['status'] ?? '');
$acctSvc = new \Rateb\App\Services\AccountingService();
$submitted = $acctSvc->isSubmittedForApproval($voucher);
$displayStatus = $acctSvc->accountingRowDisplayStatus($voucher);
$oversightOnly = rateb_accounting_final_post_oversight_only();
$badgeClass = $st === 'posted' ? 'success' : ($st === 'rejected' ? 'danger' : ($st === 'void' ? 'secondary' : ($displayStatus === 'awaiting_oversight_approval' ? 'info' : 'warning')));
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($voucher['voucher_no'] ?? ''); ?></span>
        <span class="badge bg-<?php echo $badgeClass; ?>">
            <?php echo __($displayStatus); ?>
        </span>
    </div>
    <div class="rateb-card-body">
        <p class="mb-1"><strong><?php echo __('voucher_type'); ?>:</strong> <?php echo __((string) ($voucher['voucher_type'] ?? '')); ?></p>
        <p class="mb-1"><strong><?php echo __('evaluation_date'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($voucher['voucher_date'] ?? ''); ?></p>
        <p class="mb-1"><strong><?php echo __('amount'); ?>:</strong> <?php echo number_format((float) ($voucher['amount'] ?? 0), 2); ?> SAR</p>
        <p class="mb-1"><strong><?php echo __('party_name'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($voucher['party_name'] ?? '—'); ?></p>
        <?php
        $customerLabel = '';
        if (!empty($voucher['customer_name']) || !empty($voucher['customer_name_ar'])) {
            $customerLabel = rateb_locale() === 'ar' && !empty($voucher['customer_name_ar'])
                ? (string) $voucher['customer_name_ar']
                : (string) ($voucher['customer_name'] ?? '');
            if (!empty($voucher['customer_code'])) {
                $customerLabel = trim((string) $voucher['customer_code'] . ' — ' . $customerLabel);
            }
        }
        if ($customerLabel !== '') { ?>
        <p class="mb-1"><strong><?php echo __('customer_analysis'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($customerLabel); ?></p>
        <?php } ?>
        <p class="mb-1"><strong><?php echo __('account'); ?>:</strong> <?php echo Rateb\App\Core\View::escape(($voucher['counter_code'] ?? '') . ' — ' . $counterName); ?></p>
        <?php if (!empty($voucher['reject_reason'])) { ?>
        <p class="mb-1"><strong><?php echo __('reject_reason'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($voucher['reject_reason']); ?></p>
        <?php } ?>
        <p class="mb-0"><?php echo Rateb\App\Core\View::escape($desc); ?></p>
    </div>
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="<?php echo rateb_app_url('cash-vouchers'); ?>" class="btn btn-outline-secondary"><?php echo __('cash_vouchers'); ?></a>
    <?php if (rateb_is_super_admin() && $st === 'draft') { ?>
    <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>" class="btn btn-outline-warning"><i class="fas fa-check-double"></i> <?php echo __('approvals_oversight'); ?></a>
    <?php } ?>
    <?php if (($canManage ?? false) && $st === 'draft') { ?>
    <a href="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/edit'); ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i> <?php echo __('edit'); ?></a>
    <?php if (($canManage ?? false) && $st === 'draft' && !$submitted) { ?>
    <form method="post" action="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/submit-approval'); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo Rateb\App\Core\View::escape(__('confirm_submit_for_approval')); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <input type="hidden" name="redirect_to" value="<?php echo Rateb\App\Core\View::escape(rateb_app_url('cash-vouchers/' . (int) $voucher['id'])); ?>">
        <button type="submit" class="btn btn-success rateb-btn-submit-approval"><i class="fas fa-paper-plane" aria-hidden="true"></i> <span class="rateb-btn-label"><?php echo __('submit_for_approval'); ?></span></button>
    </form>
    <?php } elseif (($canManage ?? false) && $st === 'draft' && $submitted) { ?>
    <span class="badge bg-warning text-dark align-self-center"><?php echo __('awaiting_oversight_approval'); ?></span>
    <?php } ?>
    <?php } ?>
    <?php if (($canApprove ?? false) && $st === 'posted') { ?>
    <form method="post" action="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/void'); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo __('bulk_confirm_undo'); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> <?php echo __('undo'); ?></button>
    </form>
    <?php } ?>
    <?php if (!empty($voucher['journal_entry_id'])) { ?>
    <a href="<?php echo rateb_app_url('journal-entries/' . (int) $voucher['journal_entry_id']); ?>" class="btn btn-outline-primary">
        <i class="fas fa-book"></i> <?php echo __('journal_entry'); ?>
    </a>
    <?php } ?>
</div>
