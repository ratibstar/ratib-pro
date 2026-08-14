<?php
/** @var int $companyId */
/** @var string $type */
/** @var list<array<string, mixed>> $rows */
/** @var array<string, mixed> $filters */
/** @var list<array<string, mixed>> $departments */
/** @var list<array<string, mixed>> $jobTitles */
/** @var bool $canViewSalary */
/** @var string $exportRoute */
/** @var bool $exportEnabled */
/** @var string $routePrefix */
$companyId = (int) ($companyId ?? 0);
$type = (string) ($type ?? 'employees');
$rows = $rows ?? [];
$filters = $filters ?? [];
$departments = $departments ?? [];
$jobTitles = $jobTitles ?? [];
$canViewSalary = (bool) ($canViewSalary ?? false);
$exportRoute = (string) ($exportRoute ?? '');
$exportEnabled = (bool) ($exportEnabled ?? true);
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/reports-hub'));
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
$types = [
    'employees' => __('hr_o_report_employees'),
    'attendance' => __('hr_o_report_attendance'),
    'leaves' => __('hr_o_report_leaves'),
    'payroll' => __('hr_o_report_payroll'),
    'contracts' => __('hr_o_report_contracts'),
    'recruitment' => __('hr_o_report_recruitment'),
];
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'reports-hub']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?php echo __('hr_reports_hub'); ?></h1>
    <a href="<?php echo rateb_url(rateb_app_route('hr/analytics')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_analytics'); ?></a>
</div>

<form method="get" action="<?php echo rateb_url($routePrefix); ?>" class="rateb-card mb-3">
    <div class="rateb-card-body row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label"><?php echo __('type'); ?></label>
            <select name="type" class="form-select form-select-sm">
                <?php foreach ($types as $k => $label) {
                    $sel = $type === $k ? ' selected' : '';
                    echo '<option value="' . $escape($k) . '"' . $sel . '>' . $escape($label) . '</option>';
                } ?>
            </select>
        </div>
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
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-primary w-100"><?php echo __('filter'); ?></button>
        </div>
        <div class="col-md-1">
            <?php Rateb\App\Core\View::partial('export-toolbar', [
                'exportRoute' => $exportRoute,
                'exportEnabled' => $exportEnabled && !($type === 'payroll' && !$canViewSalary),
                'inline' => true,
            ]); ?>
        </div>
    </div>
</form>

<?php if ($type === 'payroll' && !$canViewSalary) { ?>
<div class="alert alert-warning"><?php echo __('hr_360_salary_unauthorized'); ?></div>
<?php } ?>

<div class="rateb-card">
    <div class="rateb-card-header"><?php echo $escape($types[$type] ?? $type); ?> <span class="badge text-bg-light rateb-ltr-num"><?php echo count($rows); ?></span></div>
    <div class="rateb-card-body p-0">
        <?php if ($rows === []) { ?>
            <p class="text-muted small p-3 mb-0"><?php echo __('no_records'); ?></p>
        <?php } else { ?>
        <div class="table-responsive">
            <table class="table rateb-table table-sm mb-0">
                <thead>
                <tr>
                    <?php foreach (array_keys($rows[0]) as $col) {
                        if ($col === 'id') {
                            continue;
                        }
                        echo '<th>' . $escape((string) $col) . '</th>';
                    } ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <?php foreach ($row as $k => $v) {
                            if ($k === 'id') {
                                continue;
                            }
                            echo '<td>' . $escape((string) $v) . '</td>';
                        } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if (count($rows) >= 500) { ?>
            <p class="small text-muted p-2 mb-0"><?php echo __('hr_o_list_truncated'); ?></p>
        <?php } ?>
        <?php } ?>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
