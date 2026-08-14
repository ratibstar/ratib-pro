<?php
/** @var int $companyId */
/** @var list<array<string, mixed>> $items */
/** @var string $statusFilter */
/** @var string $typeFilter */
/** @var list<string> $decisionTypes */
/** @var string $csrf */
/** @var string $routePrefix */
/** @var bool $canManage */
$companyId = (int) ($companyId ?? 0);
$items = $items ?? [];
$statusFilter = (string) ($statusFilter ?? 'all');
$typeFilter = (string) ($typeFilter ?? 'all');
$decisionTypes = $decisionTypes ?? \Rateb\App\Services\HrDecisionService::TYPES;
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/decisions'));
$canManage = (bool) ($canManage ?? false);
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'decisions']);
?>
<?php if ($companyId < 1) { ?>
<div class="alert alert-warning mb-3"><?php echo __('hr_select_company_hint'); ?></div>
<?php } ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-gavel me-1"></i> <?php echo __('hr_decisions'); ?></span>
        <div class="d-flex gap-2">
            <?php if ($canManage) { ?>
            <a href="<?php echo rateb_url(rateb_app_route('hr/decisions/create')); ?>" class="btn btn-sm btn-primary"><?php echo __('hr_decision_new'); ?></a>
            <?php } ?>
            <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
        </div>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small mb-3"><?php echo __('hr_decisions_hint'); ?></p>
        <form method="get" action="<?php echo rateb_url($routePrefix); ?>" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php
                    foreach (['all' => __('all'), 'pending' => __('pending'), 'approved' => __('approved'), 'executed' => __('hr_decision_status_executed'), 'rejected' => __('rejected')] as $val => $label) {
                        $sel = $statusFilter === $val ? ' selected' : '';
                        echo '<option value="' . $escape($val) . '"' . $sel . '>' . $escape($label) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all"<?php echo $typeFilter === 'all' ? ' selected' : ''; ?>><?php echo __('all'); ?></option>
                    <?php foreach ($decisionTypes as $dt) {
                        $sel = $typeFilter === $dt ? ' selected' : '';
                        echo '<option value="' . $escape($dt) . '"' . $sel . '>' . $escape(__('hr_decision_type_' . $dt)) . '</option>';
                    } ?>
                </select>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table rateb-table mb-0 align-middle">
                <thead>
                <tr>
                    <th><?php echo __('hr_decision_no'); ?></th>
                    <th><?php echo __('employee'); ?></th>
                    <th><?php echo __('type'); ?></th>
                    <th><?php echo __('date'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($items === []) { ?>
                    <tr><td colspan="6" class="text-muted"><?php echo __('no_records'); ?></td></tr>
                <?php } ?>
                <?php foreach ($items as $row) {
                    $st = (string) ($row['status'] ?? '');
                    $id = (int) ($row['id'] ?? 0);
                    ?>
                    <tr>
                        <td class="rateb-ltr-num"><?php echo $escape((string) ($row['decision_no'] ?? '')); ?></td>
                        <td>
                            <?php echo $escape((string) ($row['employee_name'] ?? '')); ?>
                            <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($row['employee_code'] ?? '')); ?></div>
                        </td>
                        <td><?php echo $escape(__('hr_decision_type_' . (string) ($row['decision_type'] ?? ''))); ?></td>
                        <td class="rateb-ltr-num"><?php echo $escape((string) ($row['effective_date'] ?? '')); ?></td>
                        <td><?php echo $escape($st); ?></td>
                        <td class="text-end">
                            <?php if ($canManage && $st === 'approved' && empty($row['executed_at'])) { ?>
                                <form method="post" action="<?php echo rateb_url(rateb_app_route('hr/decisions/' . $id . '/execute')); ?>" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo __('hr_decision_execute'); ?></button>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
