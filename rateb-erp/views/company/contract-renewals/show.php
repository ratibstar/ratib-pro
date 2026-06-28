<?php
/** @var array<string, mixed> $item */
/** @var string $csrf */
/** @var bool $canManage */
/** @var bool $canApprove */
/** @var bool $exportEnabled */
$item = $item ?? [];
$id = (int) ($item['id'] ?? 0);
$approval = (string) ($item['manager_approval_raw'] ?? 'pending');
$status = (string) ($item['status'] ?? 'planned');
$canManage = !empty($canManage);
$canApprove = !empty($canApprove);
$exportEnabled = !empty($exportEnabled);
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-rotate me-1"></i> <?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo rateb_app_url('contract-renewals'); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
            <a href="<?php echo rateb_app_url('contract-renewals/' . $id . '/print'); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                <i class="fas fa-print"></i> <?php echo __('print'); ?>
            </a>
            <?php if ($exportEnabled) { ?>
            <a href="<?php echo rateb_url_query(rateb_app_url('contract-renewals/' . $id . '/download'), ['format' => 'pdf']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                <i class="fas fa-file-pdf"></i> <?php echo __('print_save_pdf'); ?>
            </a>
            <a href="<?php echo rateb_url_query(rateb_app_url('contract-renewals/' . $id . '/download'), ['format' => 'excel']); ?>" class="btn btn-sm btn-outline-success">
                <i class="fas fa-download"></i> Excel
            </a>
            <?php } ?>
        </div>
    </div>
    <div class="rateb-card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4"><?php echo __('record_id'); ?></dt>
            <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($item['renewal_no'] ?? '')); ?></dd>
            <dt class="col-sm-4"><?php echo __('contract_no'); ?></dt>
            <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($item['contract_no'] ?? '—')); ?></dd>
            <dt class="col-sm-4"><?php echo __('title'); ?></dt>
            <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($item['contract_title'] ?? '—')); ?></dd>
            <dt class="col-sm-4"><?php echo __('suppliers'); ?></dt>
            <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($item['supplier_name'] ?? '—')); ?></dd>
            <dt class="col-sm-4"><?php echo __('end_date'); ?></dt>
            <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::formatDate((string) ($item['contract_end_date'] ?? '—')); ?></dd>
            <dt class="col-sm-4"><?php echo __('renewal_date'); ?></dt>
            <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::formatDate((string) ($item['renewal_date'] ?? '—')); ?></dd>
            <dt class="col-sm-4"><?php echo __('new_end_date'); ?></dt>
            <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::formatDate((string) ($item['new_end_date'] ?? '—')); ?></dd>
            <dt class="col-sm-4"><?php echo __('new_value'); ?></dt>
            <dd class="col-sm-8 rateb-ltr-num"><?php echo number_format((float) ($item['new_value'] ?? 0), 2); ?></dd>
            <dt class="col-sm-4"><?php echo __('status'); ?></dt>
            <dd class="col-sm-8"><span class="badge bg-info"><?php echo Rateb\App\Core\View::escape((string) ($item['status_label'] ?? __($status))); ?></span></dd>
            <dt class="col-sm-4"><?php echo __('manager_approval'); ?></dt>
            <dd class="col-sm-8">
                <span class="badge bg-<?php echo $approval === 'approved' ? 'success' : ($approval === 'rejected' ? 'danger' : 'warning'); ?>">
                    <?php echo Rateb\App\Core\View::escape((string) ($item['manager_approval_label'] ?? __('manager_approval_' . $approval))); ?>
                </span>
            </dd>
            <?php if (!empty($item['approved_by_name']) || !empty($item['approved_at'])) { ?>
            <dt class="col-sm-4"><?php echo __('approved_by'); ?></dt>
            <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($item['approved_by_name'] ?? '—')); ?>
                <?php if (!empty($item['approved_at'])) { ?>
                <span class="text-muted small rateb-ltr-num"> — <?php echo Rateb\App\Core\View::formatDate((string) $item['approved_at']); ?></span>
                <?php } ?>
            </dd>
            <?php } ?>
            <?php if (trim((string) ($item['notes'] ?? '')) !== '') { ?>
            <dt class="col-sm-4"><?php echo __('notes'); ?></dt>
            <dd class="col-sm-8"><?php echo nl2br(Rateb\App\Core\View::escape((string) $item['notes'])); ?></dd>
            <?php } ?>
        </dl>
        <div class="mt-4 d-flex flex-wrap gap-2">
            <?php if ($canManage && $approval !== 'approved') { ?>
            <a href="<?php echo rateb_app_url('contract-renewals/' . $id . '/edit'); ?>" class="btn btn-primary"><i class="fas fa-edit"></i> <?php echo __('edit'); ?></a>
            <?php } ?>
            <?php if ($canApprove && $approval === 'pending') { ?>
            <form method="post" action="<?php echo rateb_app_url('contract-renewals/' . $id . '/approve'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <?php echo __('approve'); ?></button>
            </form>
            <form method="post" action="<?php echo rateb_app_url('contract-renewals/' . $id . '/reject'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_reject')); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="fas fa-times"></i> <?php echo __('reject'); ?></button>
            </form>
            <?php } ?>
        </div>
    </div>
</div>
