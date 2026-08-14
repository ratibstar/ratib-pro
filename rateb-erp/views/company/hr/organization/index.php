<?php
/** @var int $companyId */
/** @var array<string, mixed> $data */
/** @var array<string, mixed> $filters */
/** @var list<array<string, mixed>> $departments */
/** @var list<array<string, mixed>> $jobTitles */
/** @var string $routePrefix */
$companyId = (int) ($companyId ?? 0);
$data = $data ?? [];
$filters = $filters ?? [];
$departments = $departments ?? [];
$jobTitles = $jobTitles ?? [];
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/organization'));
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'organization']);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/hr-module.css'); ?>">

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo __('hr_organization'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('hr_organization_hint'); ?></p>
    </div>
    <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_command_center'); ?></a>
</div>

<form method="get" action="<?php echo rateb_url($routePrefix); ?>" class="rateb-card mb-3">
    <div class="rateb-card-body row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label"><?php echo __('department'); ?></label>
            <select name="department_id" class="form-select form-select-sm">
                <option value="0"><?php echo __('all'); ?></option>
                <?php foreach ($departments as $d) {
                    $sel = (int) ($filters['department_id'] ?? 0) === (int) ($d['id'] ?? 0) ? ' selected' : '';
                    echo '<option value="' . (int) ($d['id'] ?? 0) . '"' . $sel . '>' . $escape((string) ($d['name'] ?? '')) . '</option>';
                } ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><?php echo __('job_title'); ?></label>
            <select name="job_title_id" class="form-select form-select-sm">
                <option value="0"><?php echo __('all'); ?></option>
                <?php foreach ($jobTitles as $j) {
                    $sel = (int) ($filters['job_title_id'] ?? 0) === (int) ($j['id'] ?? 0) ? ' selected' : '';
                    echo '<option value="' . (int) ($j['id'] ?? 0) . '"' . $sel . '>' . $escape((string) ($j['name'] ?? '')) . '</option>';
                } ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><?php echo __('status'); ?></label>
            <select name="status" class="form-select form-select-sm">
                <?php foreach (['' => __('all'), 'active' => __('active'), 'inactive' => __('inactive'), 'terminated' => __('terminated')] as $val => $label) {
                    $sel = (string) ($filters['status'] ?? '') === (string) $val ? ' selected' : '';
                    echo '<option value="' . $escape((string) $val) . '"' . $sel . '>' . $escape($label) . '</option>';
                } ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary"><?php echo __('filter'); ?></button>
        </div>
    </div>
</form>

<div class="mb-3 small text-muted">
    <?php echo __('hr_employees'); ?>: <strong class="rateb-ltr-num"><?php echo (int) ($data['totals']['employees'] ?? 0); ?></strong>
    · <?php echo __('hr_departments'); ?>: <strong class="rateb-ltr-num"><?php echo (int) ($data['totals']['departments'] ?? 0); ?></strong>
    · <?php echo __('hr_organization_reporting_note'); ?>
</div>

<?php
$deptBlocks = is_array($data['departments'] ?? null) ? $data['departments'] : [];
if ($deptBlocks === [] && empty($data['unassigned']['employees'])) {
    echo '<div class="rateb-card"><div class="rateb-card-body text-muted">' . $escape(__('hr_o_empty_org')) . '</div></div>';
}
foreach ($deptBlocks as $block) { ?>
<div class="rateb-card mb-3 rateb-hr-org-dept">
    <div class="rateb-card-header d-flex justify-content-between">
        <span><?php echo $escape((string) ($block['name'] ?? '')); ?></span>
        <span class="badge text-bg-secondary rateb-ltr-num"><?php echo (int) ($block['employee_count'] ?? 0); ?></span>
    </div>
    <div class="rateb-card-body p-0">
        <?php $emps = is_array($block['employees'] ?? null) ? $block['employees'] : [];
        if ($emps === []) {
            echo '<p class="text-muted small p-3 mb-0">' . $escape(__('no_records')) . '</p>';
        } else { ?>
        <div class="table-responsive">
            <table class="table rateb-table table-sm mb-0">
                <thead><tr>
                    <th><?php echo __('employee'); ?></th>
                    <th><?php echo __('job_title'); ?></th>
                    <th><?php echo __('hr_360_manager'); ?></th>
                    <th><?php echo __('status'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($emps as $e) { ?>
                    <tr>
                        <td>
                            <a href="<?php echo $escape((string) ($e['360_url'] ?? '#')); ?>">
                                <?php echo $escape((string) ($e['name'] ?? '')); ?>
                            </a>
                            <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($e['employee_code'] ?? '')); ?></div>
                        </td>
                        <td><?php echo $escape((string) ($e['job_title'] ?? '')); ?></td>
                        <td><?php echo $escape((string) ($e['manager_name'] ?? '') !== '' ? (string) $e['manager_name'] : '—'); ?></td>
                        <td><?php echo $escape((string) ($e['status'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($block['truncated'])) { ?>
            <p class="small text-muted p-2 mb-0"><?php echo __('hr_o_list_truncated'); ?></p>
        <?php } ?>
        <?php } ?>
    </div>
</div>
<?php }

$un = is_array($data['unassigned'] ?? null) ? $data['unassigned'] : [];
$unEmps = is_array($un['employees'] ?? null) ? $un['employees'] : [];
if ($unEmps !== [] || (int) ($un['employee_count'] ?? 0) > 0) { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('hr_o_unassigned'); ?> <span class="badge text-bg-light rateb-ltr-num"><?php echo (int) ($un['employee_count'] ?? 0); ?></span></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table table-sm mb-0">
                <tbody>
                <?php foreach ($unEmps as $e) { ?>
                    <tr>
                        <td><a href="<?php echo $escape((string) ($e['360_url'] ?? '#')); ?>"><?php echo $escape((string) ($e['name'] ?? '')); ?></a></td>
                        <td class="rateb-ltr-num"><?php echo $escape((string) ($e['employee_code'] ?? '')); ?></td>
                        <td><?php echo $escape((string) ($e['status'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
