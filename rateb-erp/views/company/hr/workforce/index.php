<?php
/** @var int $companyId */
/** @var array<string,mixed> $dashboard */
/** @var array<string,mixed> $filters */
/** @var list<array<string,mixed>> $departments */
/** @var list<array<string,mixed>> $jobTitles */
/** @var bool $canViewSalary */
/** @var bool $schemaReady */
/** @var string $csrf */
/** @var string $routePrefix */
/** @var string $exportRoute */
/** @var bool $exportEnabled */
$companyId = (int) ($companyId ?? 0);
$dashboard = $dashboard ?? [];
$filters = $filters ?? [];
$departments = $departments ?? [];
$jobTitles = $jobTitles ?? [];
$canViewSalary = (bool) ($canViewSalary ?? false);
$schemaReady = (bool) ($schemaReady ?? false);
$csrf = (string) ($csrf ?? '');
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/workforce'));
$exportRoute = (string) ($exportRoute ?? '');
$exportEnabled = (bool) ($exportEnabled ?? true);
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
$planning = is_array($dashboard['planning'] ?? null) ? $dashboard['planning'] : [];
$attrition = is_array($dashboard['attrition'] ?? null) ? $dashboard['attrition'] : [];
$cost = is_array($dashboard['cost'] ?? null) ? $dashboard['cost'] : [];
$risk = is_array($dashboard['risk'] ?? null) ? $dashboard['risk'] : [];
$succession = is_array($dashboard['succession'] ?? null) ? $dashboard['succession'] : [];
$hiring = is_array($dashboard['hiring'] ?? null) ? $dashboard['hiring'] : [];
$saudi = is_array($dashboard['saudi'] ?? null) ? $dashboard['saudi'] : [];
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'workforce']);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/hr-module.css'); ?>">

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo __('hr_workforce_intelligence'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('hr_workforce_intelligence_hint'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php Rateb\App\Core\View::partial('export-toolbar', [
            'exportRoute' => $exportRoute,
            'exportEnabled' => $exportEnabled,
        ]); ?>
        <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_command_center'); ?></a>
    </div>
</div>

<?php if (!$schemaReady) { ?>
<div class="alert alert-warning"><?php echo __('hr_s_schema_pending'); ?></div>
<?php } ?>

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
            <label class="form-label"><?php echo __('hr_r_employment_type'); ?></label>
            <input type="text" name="employment_type" class="form-control form-control-sm" value="<?php echo $escape((string) ($filters['employment_type'] ?? '')); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo __('hr_r_classification'); ?></label>
            <select name="saudi_classification" class="form-select form-select-sm">
                <option value=""><?php echo __('all'); ?></option>
                <option value="saudi"<?php echo ($filters['saudi_classification'] ?? '') === 'saudi' ? ' selected' : ''; ?>><?php echo __('hr_r_saudi'); ?></option>
                <option value="non_saudi"<?php echo ($filters['saudi_classification'] ?? '') === 'non_saudi' ? ' selected' : ''; ?>><?php echo __('hr_r_non_saudi'); ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo __('from'); ?></label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $escape((string) ($filters['date_from'] ?? '')); ?>">
        </div>
        <div class="col-md-1">
            <label class="form-label"><?php echo __('to'); ?></label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $escape((string) ($filters['date_to'] ?? '')); ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-primary w-100"><?php echo __('filter'); ?></button>
        </div>
    </div>
</form>

<div class="row g-2 g-md-3 mb-3">
    <?php
    $tiles = [
        [__('hr_s_headcount'), (string) (int) ($dashboard['headcount'] ?? 0)],
        [__('hr_s_turnover'), number_format((float) ($dashboard['turnover_pct'] ?? 0), 1) . '%'],
        [__('hr_s_workforce_gap'), (string) (int) ($dashboard['workforce_gap'] ?? 0)],
        [__('hr_s_contract_risk'), (string) (int) ($dashboard['contract_risk'] ?? 0)],
        [__('hr_s_attendance_risk'), (string) (int) ($dashboard['attendance_risk'] ?? 0)],
        [__('hr_s_hiring'), (string) (int) ($dashboard['hiring_hired'] ?? 0)],
        [__('hr_saudi_readiness'), (string) (int) ($dashboard['saudi_readiness_pct'] ?? 0) . '%'],
    ];
    if ($canViewSalary && ($dashboard['payroll_cost'] ?? null) !== null) {
        $tiles[] = [__('hr_s_payroll_cost'), number_format((float) $dashboard['payroll_cost'], 2)];
    }
    foreach ($tiles as [$label, $val]) { ?>
        <div class="col-6 col-md-3">
            <div class="rateb-stat-card">
                <div class="rateb-stat-label"><?php echo $escape($label); ?></div>
                <div class="rateb-stat-value rateb-ltr-num"><?php echo $escape($val); ?></div>
            </div>
        </div>
    <?php } ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_s_workforce_planning'); ?></div>
            <div class="rateb-card-body">
                <p class="mb-1"><?php echo __('hr_s_current'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($planning['current_headcount'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_s_target'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($planning['target_headcount'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_s_gap'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($planning['gap'] ?? 0); ?></span></p>
                <p class="mb-3"><?php echo __('hr_s_vacancies'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($planning['vacancies'] ?? 0); ?></span></p>
                <form method="post" action="<?php echo rateb_url(rateb_app_route('hr/workforce/plan')); ?>" class="row g-2">
                    <input type="hidden" name="_token" value="<?php echo $escape($csrf); ?>">
                    <div class="col-4"><label class="form-label"><?php echo __('year'); ?></label><input type="number" name="period_year" class="form-control form-control-sm" value="<?php echo (int) ($planning['period_year'] ?? date('Y')); ?>"></div>
                    <div class="col-4"><label class="form-label"><?php echo __('month'); ?></label><input type="number" name="period_month" class="form-control form-control-sm" value="<?php echo (int) ($planning['period_month'] ?? date('n')); ?>" min="0" max="12"></div>
                    <div class="col-4"><label class="form-label"><?php echo __('hr_s_target'); ?></label><input type="number" name="target_headcount" class="form-control form-control-sm" value="<?php echo (int) ($planning['target_headcount'] ?? 0); ?>" min="0"></div>
                    <div class="col-12"><button type="submit" class="btn btn-sm btn-primary" <?php echo $schemaReady ? '' : 'disabled'; ?>><?php echo __('hr_s_save_plan'); ?></button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_s_attrition'); ?></div>
            <div class="rateb-card-body">
                <p class="mb-1"><?php echo __('hr_s_hires'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($attrition['hires'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_s_terminations'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($attrition['terminations'] ?? 0); ?></span></p>
                <p class="mb-2"><?php echo __('hr_s_turnover'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo $escape(number_format((float) ($attrition['turnover_pct'] ?? 0), 2)); ?>%</span></p>
                <p class="text-muted small"><?php echo $escape((string) ($attrition['source'] ?? '')); ?></p>
                <?php $attrDept = is_array($attrition['by_department'] ?? null) ? $attrition['by_department'] : []; ?>
                <?php if ($attrDept !== []) { ?>
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr><th><?php echo __('department'); ?></th><th class="text-end"><?php echo __('hr_s_hires'); ?></th><th class="text-end"><?php echo __('hr_s_terminations'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($attrDept as $row) { ?>
                            <tr>
                                <td><?php echo $escape((string) ($row['department_name'] ?? '')); ?></td>
                                <td class="text-end rateb-ltr-num"><?php echo (int) ($row['hires'] ?? 0); ?></td>
                                <td class="text-end rateb-ltr-num"><?php echo (int) ($row['terminations'] ?? 0); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_s_cost_analytics'); ?></div>
            <div class="rateb-card-body">
                <?php if (!empty($cost['salary_gated'])) { ?>
                    <p class="text-muted mb-0"><?php echo __('hr_r_salary_privacy'); ?></p>
                <?php } else { ?>
                    <p class="mb-1"><?php echo __('hr_s_payroll_cost'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo $escape(number_format((float) ($cost['payroll_net'] ?? 0), 2)); ?></span></p>
                    <p class="mb-1"><?php echo __('hr_s_allowances'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo $escape(number_format((float) ($cost['allowances_total'] ?? 0), 2)); ?></span></p>
                    <p class="mb-1"><?php echo __('hr_s_deductions'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo $escape(number_format((float) ($cost['deductions_total'] ?? 0), 2)); ?></span></p>
                    <p class="mb-1"><?php echo __('hr_s_gosi_employer'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo $escape(number_format((float) ($cost['gosi_modeled_employer'] ?? 0), 2)); ?></span></p>
                    <p class="mb-0"><?php echo __('hr_s_employer_cost'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo $escape(number_format((float) ($cost['employer_cost'] ?? 0), 2)); ?></span></p>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_s_employee_risk'); ?></div>
            <div class="rateb-card-body">
                <p class="mb-1"><?php echo __('hr_s_contract_risk'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($risk['contracts_expiring'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_r_missing_data'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($risk['missing_saudi_data'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_s_frequent_absent'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($risk['frequent_absent'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_s_frequent_late'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($risk['frequent_late'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_s_overdue_requests'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($risk['overdue_requests'] ?? 0); ?></span></p>
                <p class="mb-0"><?php echo __('hr_r_gosi_exceptions'); ?> / <?php echo __('hr_r_wps_exceptions'); ?>:
                    <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($risk['gosi_exceptions'] ?? 0); ?> / <?php echo (int) ($risk['wps_exceptions'] ?? 0); ?></span>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header d-flex justify-content-between">
                <span><?php echo __('hr_s_succession'); ?></span>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_url(rateb_app_route('hr/succession')); ?>"><?php echo __('hr_succession'); ?></a>
            </div>
            <div class="rateb-card-body">
                <p class="mb-1"><?php echo __('hr_s_critical_positions'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($succession['critical_positions'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_s_ready_successors'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($succession['ready_successors'] ?? 0); ?></span></p>
                <p class="mb-2"><?php echo __('hr_s_vacancy_risk'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($succession['vacancy_risk'] ?? 0); ?></span></p>
                <?php $pos = is_array($succession['positions'] ?? null) ? $succession['positions'] : []; ?>
                <?php if ($pos !== []) { ?>
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr><th><?php echo __('title'); ?></th><th class="text-end"><?php echo __('hr_s_successors'); ?></th><th class="text-end"><?php echo __('hr_s_ready'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($pos, 0, 8) as $p) { ?>
                            <tr>
                                <td><?php echo $escape((string) ($p['title'] ?? '')); ?></td>
                                <td class="text-end rateb-ltr-num"><?php echo (int) ($p['successors'] ?? 0); ?></td>
                                <td class="text-end rateb-ltr-num"><?php echo (int) ($p['ready'] ?? 0); ?></td>
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
            <div class="rateb-card-header"><?php echo __('hr_s_hiring_analytics'); ?></div>
            <div class="rateb-card-body">
                <p class="mb-1"><?php echo __('hr_s_candidates'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($hiring['candidates_total'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_s_hired'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($hiring['hired'] ?? 0); ?></span></p>
                <p class="mb-1"><?php echo __('hr_s_conversions'); ?>: <span class="rateb-ltr-num fw-semibold"><?php echo (int) ($hiring['conversions'] ?? 0); ?></span></p>
                <p class="mb-2"><?php echo __('hr_s_time_to_hire'); ?>:
                    <span class="rateb-ltr-num fw-semibold"><?php echo ($hiring['avg_time_to_hire_days'] ?? null) !== null ? $escape(number_format((float) $hiring['avg_time_to_hire_days'], 1)) : '—'; ?></span>
                </p>
                <?php $funnel = is_array($hiring['funnel'] ?? null) ? $hiring['funnel'] : []; ?>
                <?php if ($funnel !== []) { ?>
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr><th><?php echo __('status'); ?></th><th class="text-end"><?php echo __('hr_o_count'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($funnel as $f) { ?>
                            <tr>
                                <td><?php echo $escape((string) ($f['status'] ?? '')); ?></td>
                                <td class="text-end rateb-ltr-num"><?php echo (int) ($f['count'] ?? 0); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
                <p class="text-muted small mt-2 mb-0"><?php echo __('hr_saudi_no_external_send'); ?> · <?php echo __('hr_s_saudi_exceptions'); ?>: <span class="rateb-ltr-num"><?php echo (int) ($saudi['gosi_exceptions'] ?? 0) + (int) ($saudi['wps_exceptions'] ?? 0); ?></span></p>
            </div>
        </div>
    </div>
</div>
