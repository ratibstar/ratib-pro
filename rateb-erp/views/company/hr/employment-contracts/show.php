<?php
/** @var array<string, mixed> $item */
/** @var bool $canManage */
/** @var string $csrf */
/** @var string $routePrefix */
$item = is_array($item ?? null) ? $item : [];
$canManage = (bool) ($canManage ?? false);
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/employment-contracts'));
$id = (int) ($item['id'] ?? 0);
$status = (string) ($item['status'] ?? '');
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
$stLabel = __('hr_contract_status_' . $status);
if ($stLabel === 'hr_contract_status_' . $status) {
    $stLabel = $status;
}
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employment-contracts']);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fas fa-file-signature me-1"></i>
            <?php echo __('hr_employment_contract'); ?>
            <span class="badge bg-light text-dark border ms-2"><?php echo $escape($stLabel); ?></span>
        </span>
        <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
    </div>
    <div class="rateb-card-body">
        <dl class="row small mb-4">
            <dt class="col-sm-3"><?php echo __('hr_contract_no'); ?></dt>
            <dd class="col-sm-9 rateb-ltr-num"><?php echo $escape((string) ($item['contract_no'] ?? '')); ?></dd>
            <dt class="col-sm-3"><?php echo __('hr_employees'); ?></dt>
            <dd class="col-sm-9">
                <a href="<?php echo rateb_url(rateb_app_route('hr/employees/' . (int) ($item['employee_id'] ?? 0))); ?>">
                    <?php echo $escape(trim((string) ($item['employee_name'] ?? '') . ' · ' . (string) ($item['employee_code'] ?? ''))); ?>
                </a>
            </dd>
            <dt class="col-sm-3"><?php echo __('start_date'); ?></dt>
            <dd class="col-sm-9 rateb-ltr-num"><?php echo $escape((string) ($item['start_date'] ?? '')); ?></dd>
            <dt class="col-sm-3"><?php echo __('end_date'); ?></dt>
            <dd class="col-sm-9 rateb-ltr-num"><?php echo $escape((string) (($item['end_date'] ?? '') !== '' && ($item['end_date'] ?? null) !== null ? $item['end_date'] : '—')); ?></dd>
            <dt class="col-sm-3"><?php echo __('salary'); ?></dt>
            <dd class="col-sm-9 rateb-ltr-num"><?php echo $escape(number_format((float) ($item['salary'] ?? 0), 2)); ?></dd>
            <dt class="col-sm-3"><?php echo __('hr_contract_alert_days'); ?></dt>
            <dd class="col-sm-9 rateb-ltr-num"><?php echo (int) ($item['alert_days'] ?? 30); ?></dd>
            <dt class="col-sm-3"><?php echo __('notes'); ?></dt>
            <dd class="col-sm-9"><?php echo $escape((string) (($item['notes'] ?? '') !== '' ? $item['notes'] : '—')); ?></dd>
        </dl>

        <?php if ($canManage && in_array($status, ['draft', 'active'], true)) { ?>
        <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/update'); ?>" class="border rounded p-3 mb-3">
            <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
            <h2 class="h6 mb-3"><?php echo __('edit'); ?></h2>
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small"><?php echo __('start_date'); ?></label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $escape((string) ($item['start_date'] ?? '')); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?php echo __('end_date'); ?></label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $escape((string) ($item['end_date'] ?? '')); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?php echo __('salary'); ?></label>
                    <input type="number" step="0.01" min="0" name="salary" class="form-control form-control-sm rateb-ltr-num" value="<?php echo $escape((string) ($item['salary'] ?? '0')); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?php echo __('hr_contract_alert_days'); ?></label>
                    <input type="number" min="1" max="365" name="alert_days" class="form-control form-control-sm rateb-ltr-num" value="<?php echo (int) ($item['alert_days'] ?? 30); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label small"><?php echo __('notes'); ?></label>
                    <textarea name="notes" class="form-control form-control-sm" rows="2"><?php echo $escape((string) ($item['notes'] ?? '')); ?></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-primary"><?php echo __('save'); ?></button>
                </div>
            </div>
        </form>
        <?php } ?>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($canManage && $status === 'draft') { ?>
            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/activate'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
                <button type="submit" class="btn btn-sm btn-success"><?php echo __('hr_contract_activate'); ?></button>
            </form>
            <?php } ?>
            <?php if ($canManage && $status === 'active') { ?>
            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/terminate'); ?>" class="d-inline-flex gap-2 align-items-center">
                <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
                <input type="text" name="notes" class="form-control form-control-sm" placeholder="<?php echo $escape(__('notes')); ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo __('hr_contract_terminate'); ?></button>
            </form>
            <?php } ?>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
