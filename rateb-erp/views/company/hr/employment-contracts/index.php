<?php
/** @var int $companyId */
/** @var list<array<string, mixed>> $items */
/** @var list<array<string, mixed>> $employees */
/** @var string $statusFilter */
/** @var bool $canManage */
/** @var string $csrf */
/** @var string $storeUrl */
/** @var string $routePrefix */
$companyId = (int) ($companyId ?? 0);
$items = $items ?? [];
$employees = $employees ?? [];
$statusFilter = (string) ($statusFilter ?? 'all');
$canManage = (bool) ($canManage ?? false);
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$storeUrl = (string) ($storeUrl ?? rateb_url(rateb_app_route('hr/employment-contracts')));
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/employment-contracts'));
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employment-contracts']);
?>
<?php if ($companyId < 1) { ?>
<div class="alert alert-warning mb-3"><?php echo __('hr_select_company_hint'); ?></div>
<?php } ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-file-signature me-1"></i> <?php echo __('hr_employment_contracts'); ?></span>
        <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small mb-3"><?php echo __('hr_employment_contracts_hint'); ?></p>

        <form method="get" action="<?php echo rateb_url($routePrefix); ?>" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php
                    $opts = [
                        'all' => __('all'),
                        'draft' => __('hr_contract_status_draft'),
                        'active' => __('hr_contract_status_active'),
                        'expired' => __('hr_contract_status_expired'),
                        'terminated' => __('hr_contract_status_terminated'),
                    ];
                    foreach ($opts as $val => $label) {
                        $sel = $statusFilter === $val ? ' selected' : '';
                        echo '<option value="' . $escape($val) . '"' . $sel . '>' . $escape($label) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </form>

        <?php if ($canManage) { ?>
        <form method="post" action="<?php echo $escape($storeUrl); ?>" class="border rounded p-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
            <h2 class="h6 mb-3"><?php echo __('hr_employment_contract_new'); ?></h2>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small"><?php echo __('hr_employees'); ?></label>
                    <select name="employee_id" class="form-select form-select-sm" required>
                        <option value=""><?php echo __('select'); ?></option>
                        <?php foreach ($employees as $emp) { ?>
                        <option value="<?php echo (int) ($emp['id'] ?? 0); ?>">
                            <?php echo $escape(trim((string) ($emp['name'] ?? '') . ' · ' . (string) ($emp['employee_code'] ?? ''))); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><?php echo __('start_date'); ?></label>
                    <input type="date" name="start_date" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><?php echo __('end_date'); ?></label>
                    <input type="date" name="end_date" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><?php echo __('salary'); ?></label>
                    <input type="number" step="0.01" min="0" name="salary" class="form-control form-control-sm rateb-ltr-num" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><?php echo __('hr_contract_alert_days'); ?></label>
                    <input type="number" min="1" max="365" name="alert_days" class="form-control form-control-sm rateb-ltr-num" value="30">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-primary"><?php echo __('create'); ?></button>
                </div>
            </div>
        </form>
        <?php } ?>

        <div class="table-responsive">
            <table class="table rateb-table mb-0 align-middle">
                <thead>
                <tr>
                    <th><?php echo __('hr_contract_no'); ?></th>
                    <th><?php echo __('hr_employees'); ?></th>
                    <th><?php echo __('start_date'); ?></th>
                    <th><?php echo __('end_date'); ?></th>
                    <th><?php echo __('salary'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($items === []) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    foreach ($items as $item) {
                        $st = (string) ($item['status'] ?? '');
                        $stLabel = __('hr_contract_status_' . $st);
                        if ($stLabel === 'hr_contract_status_' . $st) {
                            $stLabel = $st;
                        }
                        ?>
                <tr>
                    <td class="rateb-ltr-num"><?php echo $escape((string) ($item['contract_no'] ?? '')); ?></td>
                    <td><?php echo $escape(trim((string) ($item['employee_name'] ?? '') . ' · ' . (string) ($item['employee_code'] ?? ''))); ?></td>
                    <td class="rateb-ltr-num small"><?php echo $escape((string) ($item['start_date'] ?? '')); ?></td>
                    <td class="rateb-ltr-num small"><?php echo $escape((string) (($item['end_date'] ?? '') !== '' ? $item['end_date'] : '—')); ?></td>
                    <td class="rateb-ltr-num"><?php echo $escape(number_format((float) ($item['salary'] ?? 0), 2)); ?></td>
                    <td><span class="badge bg-light text-dark border"><?php echo $escape($stLabel); ?></span></td>
                    <td>
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_url($routePrefix . '/' . (int) ($item['id'] ?? 0)); ?>">
                            <?php echo __('view'); ?>
                        </a>
                    </td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
