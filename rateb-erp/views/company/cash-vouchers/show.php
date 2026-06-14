<?php
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
$desc = rateb_locale() === 'ar' && !empty($voucher['description_ar']) ? $voucher['description_ar'] : $voucher['description'];
$counterName = rateb_locale() === 'ar' && !empty($voucher['counter_name_ar']) ? $voucher['counter_name_ar'] : $voucher['counter_name'];
$st = (string) ($voucher['status'] ?? '');
$voucherId = (int) ($voucher['id'] ?? 0);

Rateb\App\Core\View::partial('accounting-doc-workflow', [
    'status' => $st,
    'docType' => 'voucher',
    'canManage' => ($canManage ?? false) && $st === 'draft',
    'canApprove' => ($canApprove ?? false) && in_array($st, ['draft', 'posted'], true),
    'csrf' => $csrf,
    'docId' => $voucherId,
    'postUrl' => rateb_app_url('cash-vouchers/' . $voucherId . '/post'),
    'voidUrl' => rateb_app_url('cash-vouchers/' . $voucherId . '/void'),
    'editUrl' => rateb_app_url('cash-vouchers/' . $voucherId . '/edit'),
    'deleteUrl' => rateb_app_url('cash-vouchers/' . $voucherId . '/delete'),
    'listUrl' => rateb_app_url('cash-vouchers'),
]);
?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-invoice-dollar me-2"></i><?php echo __('voucher_review'); ?> — <?php echo Rateb\App\Core\View::escape($voucher['voucher_no'] ?? ''); ?></span>
        <span class="badge bg-<?php echo $st === 'posted' ? 'success' : ($st === 'void' ? 'secondary' : 'warning'); ?>"><?php echo __($st); ?></span>
    </div>
    <div class="rateb-card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <span class="text-muted small d-block"><?php echo __('voucher_type'); ?></span>
                <strong><?php echo __((string) ($voucher['voucher_type'] ?? '')); ?></strong>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block"><?php echo __('evaluation_date'); ?></span>
                <strong><?php echo Rateb\App\Core\View::escape($voucher['voucher_date'] ?? ''); ?></strong>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block"><?php echo __('amount'); ?></span>
                <strong><?php echo number_format((float) ($voucher['amount'] ?? 0), 2); ?> SAR</strong>
            </div>
            <div class="col-md-6">
                <span class="text-muted small d-block"><?php echo __('party_name'); ?></span>
                <strong><?php echo Rateb\App\Core\View::escape($voucher['party_name'] ?? '—'); ?></strong>
            </div>
            <div class="col-md-6">
                <span class="text-muted small d-block"><?php echo __('counter_account'); ?></span>
                <strong><?php echo Rateb\App\Core\View::escape(($voucher['counter_code'] ?? '') . ' — ' . $counterName); ?></strong>
            </div>
            <div class="col-12">
                <span class="text-muted small d-block"><?php echo __('description'); ?></span>
                <strong><?php echo Rateb\App\Core\View::escape($desc); ?></strong>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($voucher['journal_entry_id'])) { ?>
<div class="mb-3">
    <a href="<?php echo rateb_app_url('journal-entries/' . (int) $voucher['journal_entry_id']); ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-book"></i> <?php echo __('view_linked_journal'); ?>
    </a>
</div>
<?php } ?>
