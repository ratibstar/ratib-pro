<?php
/** @var int $companyId */
/** @var array<string, mixed> $snapshot */
/** @var array<string, mixed> $filters */
/** @var list<array<string, mixed>> $departments */
/** @var list<array<string, mixed>> $jobTitles */
/** @var bool $canViewSalary */
/** @var string $routePrefix */
$companyId = (int) ($companyId ?? 0);
$snapshot = $snapshot ?? [];
$filters = $filters ?? [];
$departments = $departments ?? [];
$jobTitles = $jobTitles ?? [];
$canViewSalary = (bool) ($canViewSalary ?? false);
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/analytics'));
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
$hc = is_array($snapshot['headcount'] ?? null) ? $snapshot['headcount'] : [];
$att = is_array($snapshot['attendance'] ?? null) ? $snapshot['attendance'] : [];
$leaves = is_array($snapshot['leaves'] ?? null) ? $snapshot['leaves'] : [];
$ht = is_array($snapshot['hire_terminate'] ?? null) ? $snapshot['hire_terminate'] : [];
$pay = is_array($snapshot['payroll'] ?? null) ? $snapshot['payroll'] : [];
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'analytics']);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/hr-module.css'); ?>">

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo __('hr_analytics'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('hr_analytics_hint'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo rateb_url(rateb_app_route('hr/reports-hub')); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('hr_reports_hub'); ?></a>
        <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_command_center'); ?></a>
    </div>
</div>

<form method="get" class="rateb-card mb-3">
    <div class="rateb-card-body row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label"><?php echo __('department'); ?></label>
            <select name="department_id" class="form-select form-select-sm">
                <option value="0"><?php echo __('all'); ?></option>
                <?php foreach ($departments as $d) {
                    $sel = (int) ($filters['department_id'] ?? 0) === (int) ($d['id'] ?? 0) ? ' selected' : '';
                    echo '<option value="' . (int) ($d['id'] ?? 0) . '"' . $sel . '>' . $escape((string) ($d['name'] ?? '')) . '</option>';
                } ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo __('job_title'); ?></label>
            <select name="job_title_id" class="form-select form-select-sm">
                <option value="0"><?php echo __('all'); ?></option>
                <?php foreach ($jobTitles as $j) {
                    $sel = (int) ($filters['job_title_id'] ?? 0) === (int) ($j['id'] ?? 0) ? ' selected' : '';
                    echo '<option value="' . (int) ($j['id'] ?? 0) . '"' . $sel . '>' . $escape((string) ($j['name'] ?? '')) . '</option>';
                } ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo __('status'); ?></label>
            <select name="status" class="form-select form-select-sm">
                <?php foreach (['' => __('all'), 'active' => __('active'), 'inactive' => __('inactive'), 'terminated' => __('terminated')] as $val => $label) {
                    $sel = (string) ($filters['status'] ?? '') === (string) $val ? ' selected' : '';
                    echo '<option value="' . $escape((string) $val) . '"' . $sel . '>' . $escape($label) . '</option>';
                } ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo __('from'); ?></label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $escape((string) ($filters['date_from'] ?? '')); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo __('to'); ?></label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $escape((string) ($filters['date_to'] ?? '')); ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100"><?php echo __('filter'); ?></button>
        </div>
    </div>
</form>

<div class="row g-2 g-md-3 mb-3">
    <?php
    $tiles = [
        [__('hr_employees'), (int) ($hc['total'] ?? 0)],
        [__('hr_active_employees'), (int) ($hc['active'] ?? 0)],
        [__('hr_o_hired_period'), (int) ($ht['hired'] ?? 0)],
        [__('hr_o_terminated_total'), (int) ($ht['terminated'] ?? 0)],
        [__('hr_absent_today'), (int) ($att['absent'] ?? 0)],
        [__('hr_cc_late_today'), (int) ($att['late'] ?? 0)],
        [__('hr_pending_leaves'), (int) ($leaves['pending'] ?? 0)],
        [__('hr_cc_contracts_expiring'), count(is_array($snapshot['contracts_expiring'] ?? null) ? $snapshot['contracts_expiring'] : [])],
    ];
    foreach ($tiles as [$label, $val]) { ?>
        <div class="col-6 col-md-3">
            <div class="rateb-stat-card">
                <div class="rateb-stat-label"><?php echo $escape($label); ?></div>
                <div class="rateb-stat-value rateb-ltr-num"><?php echo (int) $val; ?></div>
            </div>
        </div>
    <?php } ?>
</div>

<?php if ($canViewSalary && ($pay['net_total'] ?? null) !== null) { ?>
<div class="alert alert-light border mb-3">
    <?php echo __('hr_o_payroll_net_period'); ?>:
    <strong class="rateb-ltr-num"><?php echo $escape(number_format((float) $pay['net_total'], 2)); ?></strong>
    (<?php echo (int) ($pay['periods'] ?? 0); ?> <?php echo __('hr_o_periods'); ?>)
</div>
<?php } ?>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_o_by_department'); ?></div>
            <div class="rateb-card-body p-0">
                <?php $byDept = is_array($snapshot['by_department'] ?? null) ? $snapshot['by_department'] : []; ?>
                <?php if ($byDept === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('hr_o_empty_dept'); ?></p>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr><th><?php echo __('department'); ?></th><th class="text-end"><?php echo __('hr_o_count'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($byDept as $row) { ?>
                            <tr>
                                <td><?php echo $escape((string) ($row['department_name'] ?? '')); ?></td>
                                <td class="text-end rateb-ltr-num"><?php echo (int) ($row['count'] ?? 0); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_o_attention'); ?></div>
            <div class="rateb-card-body p-0">
                <?php $attn = is_array($snapshot['attention'] ?? null) ? $snapshot['attention'] : []; ?>
                <?php if ($attn === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('hr_o_no_attention'); ?></p>
                <?php } else { ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($attn as $item) { ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="<?php echo $escape((string) ($item['url'] ?? '#')); ?>"><?php echo $escape(__((string) ($item['label'] ?? ''))); ?></a>
                            <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($item['count'] ?? 0); ?></span>
                        </li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('hr_o_recruitment_summary'); ?></div>
    <div class="rateb-card-body p-0">
        <?php $rec = is_array($snapshot['recruitment'] ?? null) ? $snapshot['recruitment'] : []; ?>
        <?php if ($rec === []) { ?>
            <p class="text-muted small p-3 mb-0"><?php echo __('no_records'); ?></p>
        <?php } else { ?>
        <div class="table-responsive">
            <table class="table rateb-table table-sm mb-0">
                <thead><tr><th><?php echo __('status'); ?></th><th class="text-end"><?php echo __('hr_o_count'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rec as $row) { ?>
                    <tr>
                        <td><?php echo $escape((string) ($row['status'] ?? '')); ?></td>
                        <td class="text-end rateb-ltr-num"><?php echo (int) ($row['count'] ?? 0); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
