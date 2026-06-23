<?php
/** @var array<string, mixed> $item */
/** @var array<int, array<string, mixed>> $formFields */
/** @var array<string, mixed> $lookups */
/** @var string $csrf */
/** @var bool $canApprove */
$item = $item ?? [];
$id = (int) ($item['id'] ?? 0);
$approval = (string) ($item['manager_approval'] ?? 'pending');
$canApprove = !empty($canApprove);
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo __('edit'); ?> — <?php echo Rateb\App\Core\View::escape((string) ($item['renewal_no'] ?? '')); ?></span>
        <a href="<?php echo rateb_app_url('contract-renewals'); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('contract_renewals'); ?></a>
    </div>
    <div class="rateb-card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label text-muted small"><?php echo __('manager_approval'); ?></label>
                <div>
                    <span class="badge bg-<?php echo $approval === 'approved' ? 'success' : ($approval === 'rejected' ? 'danger' : 'warning'); ?>">
                        <?php echo Rateb\App\Core\View::escape(__('manager_approval_' . $approval)); ?>
                    </span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small"><?php echo __('status'); ?></label>
                <div><?php echo Rateb\App\Core\View::escape(__((string) ($item['status'] ?? 'planned'))); ?></div>
            </div>
        </div>
        <form method="post" action="<?php echo rateb_app_url('contract-renewals/' . $id); ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php foreach ($formFields as $field) {
                $name = (string) ($field['name'] ?? '');
                $value = (string) ($item[$name] ?? ($field['default'] ?? ''));
                if ($name === 'new_end_date' && $value === '0000-00-00') {
                    $value = '';
                }
                Rateb\App\Core\View::partial('form-field', [
                    'field' => $field,
                    'value' => $value,
                    'lookups' => $lookups,
                ]);
            } ?>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_app_url('contract-renewals'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
        <?php if ($canApprove && $approval === 'pending') { ?>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <form method="post" action="<?php echo rateb_app_url('contract-renewals/' . $id . '/approve'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <?php echo __('approve'); ?></button>
            </form>
            <form method="post" action="<?php echo rateb_app_url('contract-renewals/' . $id . '/reject'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_reject')); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="fas fa-times"></i> <?php echo __('reject'); ?></button>
            </form>
        </div>
        <?php } ?>
    </div>
</div>
