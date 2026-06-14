<?php
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
$desc = rateb_locale() === 'ar' && !empty($voucher['description_ar']) ? $voucher['description_ar'] : $voucher['description'];
$counterName = rateb_locale() === 'ar' && !empty($voucher['counter_name_ar']) ? $voucher['counter_name_ar'] : $voucher['counter_name'];
$st = (string) ($voucher['status'] ?? '');
$displayStatus = $st === 'draft' ? 'pending' : ($st === 'posted' ? 'approved' : $st);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($voucher['voucher_no'] ?? ''); ?></span>
        <span class="badge bg-<?php echo $st === 'posted' ? 'success' : ($st === 'rejected' ? 'danger' : ($st === 'void' ? 'secondary' : 'warning')); ?>">
            <?php echo __($displayStatus); ?>
        </span>
    </div>
    <div class="rateb-card-body">
        <p class="mb-1"><strong><?php echo __('voucher_type'); ?>:</strong> <?php echo __((string) ($voucher['voucher_type'] ?? '')); ?></p>
        <p class="mb-1"><strong><?php echo __('evaluation_date'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($voucher['voucher_date'] ?? ''); ?></p>
        <p class="mb-1"><strong><?php echo __('amount'); ?>:</strong> <?php echo number_format((float) ($voucher['amount'] ?? 0), 2); ?> SAR</p>
        <p class="mb-1"><strong><?php echo __('party_name'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($voucher['party_name'] ?? '—'); ?></p>
        <p class="mb-1"><strong><?php echo __('account'); ?>:</strong> <?php echo Rateb\App\Core\View::escape(($voucher['counter_code'] ?? '') . ' — ' . $counterName); ?></p>
        <?php if (!empty($voucher['reject_reason'])) { ?>
        <p class="mb-1"><strong><?php echo __('reject_reason'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($voucher['reject_reason']); ?></p>
        <?php } ?>
        <p class="mb-0"><?php echo Rateb\App\Core\View::escape($desc); ?></p>
    </div>
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="<?php echo rateb_app_url('cash-vouchers'); ?>" class="btn btn-outline-secondary"><?php echo __('cash_vouchers'); ?></a>
    <a href="<?php echo rateb_app_url('accounting/voucher-approval'); ?>" class="btn btn-outline-secondary"><i class="fas fa-check-double"></i> <?php echo __('voucher_approval'); ?></a>
    <?php if (($canManage ?? false) && $st === 'draft') { ?>
    <a href="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/edit'); ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i> <?php echo __('edit'); ?></a>
    <?php } ?>
    <?php if (($canApprove ?? false) && $st === 'draft') { ?>
    <form method="post" action="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/post'); ?>" class="d-inline">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <?php echo __('approve_voucher'); ?></button>
    </form>
    <form method="post" action="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/reject'); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo __('bulk_confirm_reject'); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <input type="text" name="reject_reason" class="form-control form-control-sm d-inline-block" style="width:10rem" placeholder="<?php echo Rateb\App\Core\View::escape(__('reject_reason')); ?>">
        <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> <?php echo __('reject'); ?></button>
    </form>
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
