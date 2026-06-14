<?php
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
$desc = rateb_locale() === 'ar' && !empty($voucher['description_ar']) ? $voucher['description_ar'] : $voucher['description'];
$counterName = rateb_locale() === 'ar' && !empty($voucher['counter_name_ar']) ? $voucher['counter_name_ar'] : $voucher['counter_name'];
$st = (string) ($voucher['status'] ?? '');
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($voucher['voucher_no'] ?? ''); ?></span>
        <span class="badge bg-<?php echo $st === 'posted' ? 'success' : ($st === 'void' ? 'secondary' : 'warning'); ?>"><?php echo __($st); ?></span>
    </div>
    <div class="rateb-card-body">
        <p class="mb-1"><strong><?php echo __('voucher_type'); ?>:</strong> <?php echo __((string) ($voucher['voucher_type'] ?? '')); ?></p>
        <p class="mb-1"><strong><?php echo __('evaluation_date'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($voucher['voucher_date'] ?? ''); ?></p>
        <p class="mb-1"><strong><?php echo __('amount'); ?>:</strong> <?php echo number_format((float) ($voucher['amount'] ?? 0), 2); ?> SAR</p>
        <p class="mb-1"><strong><?php echo __('party_name'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($voucher['party_name'] ?? '—'); ?></p>
        <p class="mb-1"><strong><?php echo __('account'); ?>:</strong> <?php echo Rateb\App\Core\View::escape(($voucher['counter_code'] ?? '') . ' — ' . $counterName); ?></p>
        <p class="mb-0"><?php echo Rateb\App\Core\View::escape($desc); ?></p>
    </div>
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="<?php echo rateb_app_url('cash-vouchers'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    <?php if (($canManage ?? false) && $st === 'draft') { ?>
    <a href="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/edit'); ?>" class="btn btn-outline-primary">
        <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
    </a>
    <form method="post" action="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/delete'); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo __('bulk_confirm_delete_drafts'); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i> <?php echo __('delete'); ?></button>
    </form>
    <?php } ?>
    <?php if (($canApprove ?? false) && $st === 'draft') { ?>
    <form method="post" action="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/post'); ?>" class="d-inline">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <?php echo __('approve_voucher'); ?></button>
    </form>
    <?php } ?>
    <?php if (($canApprove ?? false) && $st === 'posted') { ?>
    <form method="post" action="<?php echo rateb_app_url('cash-vouchers/' . (int) $voucher['id'] . '/void'); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo __('journal_void_confirm'); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-ban"></i> <?php echo __('void_entry'); ?></button>
    </form>
    <?php } ?>
    <?php if (!empty($voucher['journal_entry_id'])) { ?>
    <a href="<?php echo rateb_app_url('journal-entries/' . (int) $voucher['journal_entry_id']); ?>" class="btn btn-outline-primary">
        <i class="fas fa-book"></i> <?php echo __('journal_entry'); ?>
    </a>
    <?php } ?>
    <?php if ($st === 'draft' && !($canApprove ?? false)) { ?>
    <p class="text-muted small mb-0 align-self-center"><i class="fas fa-lock me-1"></i><?php echo __('accounting_perm_approve_hint'); ?></p>
    <?php } ?>
</div>
