<?php
/** @var array<string, mixed> $employee */
/** @var array<string, mixed> $attendance_ytd */
/** @var array<int, array<string, mixed>> $recent_leaves */
/** @var array<int, array<string, mixed>> $leave_balances */
$employee = $employee ?? [];
$routePrefix = $routePrefix ?? rateb_app_route('hr/employees');
$canManage = $canManage ?? true;
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employees']);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape((string) ($employee['name'] ?? '')); ?></span>
        <div class="d-flex gap-2">
            <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('back'); ?></a>
            <?php if ($canManage) { ?>
            <a href="<?php echo rateb_url($routePrefix . '/' . (int) ($employee['id'] ?? 0) . '/edit'); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
            </a>
            <?php } ?>
        </div>
    </div>
    <div class="rateb-card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?php echo __('employee_code'); ?></dt>
                    <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['employee_code'] ?? '')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('email'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($employee['email'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('phone'); ?></dt>
                    <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['phone'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('job_title'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($employee['job_title'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('hire_date'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($employee['hire_date'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('salary_base'); ?></dt>
                    <dd class="col-sm-8 rateb-ltr-num"><?php echo number_format((float) ($employee['salary_base'] ?? 0), 2); ?></dd>
                    <dt class="col-sm-4"><?php echo __('status'); ?></dt>
                    <dd class="col-sm-8"><?php echo __((string) ($employee['status'] ?? 'active')); ?></dd>
                </dl>
            </div>
            <div class="col-md-6">
                <h6 class="mb-3"><?php echo __('attendance_ytd'); ?></h6>
                <div class="row g-2 mb-4">
                    <div class="col-4"><div class="rateb-stat-card p-2"><div class="small text-muted"><?php echo __('present'); ?></div><div class="fw-bold"><?php echo (int) ($attendance_ytd['present'] ?? 0); ?></div></div></div>
                    <div class="col-4"><div class="rateb-stat-card p-2"><div class="small text-muted"><?php echo __('absent'); ?></div><div class="fw-bold"><?php echo (int) ($attendance_ytd['absent'] ?? 0); ?></div></div></div>
                    <div class="col-4"><div class="rateb-stat-card p-2"><div class="small text-muted"><?php echo __('leave'); ?></div><div class="fw-bold"><?php echo (int) ($attendance_ytd['on_leave'] ?? 0); ?></div></div></div>
                </div>
                <h6 class="mb-2"><?php echo __('leave_balances'); ?></h6>
                <?php if (empty($leave_balances)) { ?>
                <p class="text-muted small"><?php echo __('no_records'); ?></p>
                <?php } else { ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($leave_balances as $bal) {
                        $remaining = (float) ($bal['entitled_days'] ?? 0) - (float) ($bal['used_days'] ?? 0);
                        ?>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span><?php echo Rateb\App\Core\View::escape((string) ($bal['leave_type_name'] ?? '')); ?></span>
                        <span class="rateb-ltr-num"><?php echo number_format($remaining, 1); ?> / <?php echo number_format((float) ($bal['entitled_days'] ?? 0), 1); ?></span>
                    </li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($recent_leaves)) { ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('recent_leaves'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead><tr>
                    <th><?php echo __('leave_type'); ?></th>
                    <th><?php echo __('start_date'); ?></th>
                    <th><?php echo __('end_date'); ?></th>
                    <th><?php echo __('days'); ?></th>
                    <th><?php echo __('status'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($recent_leaves as $lv) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($lv['leave_type_name'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($lv['start_date'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($lv['end_date'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($lv['days'] ?? '')); ?></td>
                    <td><?php echo __((string) ($lv['status'] ?? '')); ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
