<?php
/** @var int $companyId */
/** @var array<string,mixed> $summary */
/** @var list<array<string,mixed>> $profiles */
/** @var list<array<string,mixed>> $batches */
/** @var bool $canViewSalary */
/** @var bool $schemaReady */
/** @var string $csrf */
/** @var string $routePrefix */
/** @var int $year */
/** @var int $month */
$companyId = (int) ($companyId ?? 0);
$summary = $summary ?? [];
$profiles = $profiles ?? [];
$batches = $batches ?? [];
$canViewSalary = (bool) ($canViewSalary ?? false);
$schemaReady = (bool) ($schemaReady ?? false);
$csrf = (string) ($csrf ?? '');
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/saudi-compliance'));
$year = (int) ($year ?? date('Y'));
$month = (int) ($month ?? date('n'));
$tab = trim((string) ($_GET['tab'] ?? 'employees'));
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'saudi-compliance']);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/hr-module.css'); ?>">

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo __('hr_saudi_compliance'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('hr_saudi_compliance_hint'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo rateb_url(rateb_app_route('hr/saudi-compliance/reports')); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('hr_saudi_reports'); ?></a>
        <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_command_center'); ?></a>
    </div>
</div>

<?php if (!$schemaReady) { ?>
<div class="alert alert-warning"><?php echo __('hr_r_schema_pending'); ?></div>
<?php } ?>

<div class="row g-2 g-md-3 mb-3">
    <?php
    $tiles = [
        [__('hr_r_readiness_pct'), ((int) ($summary['readiness_pct'] ?? 0)) . '%'],
        [__('hr_active_employees'), (string) (int) ($summary['active_employees'] ?? 0)],
        [__('hr_r_missing_data'), (string) (int) ($summary['missing_data'] ?? 0)],
        [__('hr_r_gosi_exceptions'), (string) (int) ($summary['gosi_exceptions'] ?? 0)],
        [__('hr_r_wps_exceptions'), (string) (int) ($summary['wps_exceptions'] ?? 0)],
        [__('hr_r_external_sent'), '0 / OFF'],
    ];
    foreach ($tiles as [$label, $val]) { ?>
        <div class="col-6 col-md-2">
            <div class="rateb-stat-card">
                <div class="rateb-stat-label"><?php echo $escape($label); ?></div>
                <div class="rateb-stat-value rateb-ltr-num"><?php echo $escape($val); ?></div>
            </div>
        </div>
    <?php } ?>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link<?php echo $tab === 'employees' ? ' active' : ''; ?>" href="?tab=employees"><?php echo __('hr_employees'); ?></a></li>
    <li class="nav-item"><a class="nav-link<?php echo $tab === 'gosi' ? ' active' : ''; ?>" href="?tab=gosi"><?php echo __('hr_r_gosi'); ?></a></li>
    <li class="nav-item"><a class="nav-link<?php echo $tab === 'wps' ? ' active' : ''; ?>" href="?tab=wps"><?php echo __('hr_r_wps'); ?></a></li>
</ul>

<?php if ($tab === 'gosi') { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('hr_r_build_gosi'); ?></div>
    <div class="rateb-card-body">
        <p class="small text-muted"><?php echo __('hr_r_gosi_build_hint'); ?></p>
        <form method="post" action="<?php echo rateb_url(rateb_app_route('hr/saudi-compliance/gosi-build')); ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_token" value="<?php echo $escape($csrf); ?>">
            <div class="col-auto">
                <label class="form-label"><?php echo __('year'); ?></label>
                <input type="number" name="period_year" class="form-control form-control-sm" value="<?php echo (int) $year; ?>" min="2000" max="2100">
            </div>
            <div class="col-auto">
                <label class="form-label"><?php echo __('month'); ?></label>
                <input type="number" name="period_month" class="form-control form-control-sm" value="<?php echo (int) $month; ?>" min="1" max="12">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary" <?php echo $schemaReady ? '' : 'disabled'; ?>><?php echo __('hr_r_build_gosi'); ?></button>
            </div>
        </form>
    </div>
</div>
<?php } elseif ($tab === 'wps') { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('hr_r_build_wps'); ?></div>
    <div class="rateb-card-body">
        <p class="small text-muted"><?php echo __('hr_r_wps_build_hint'); ?></p>
        <form method="post" action="<?php echo rateb_url(rateb_app_route('hr/saudi-compliance/wps-build')); ?>" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="_token" value="<?php echo $escape($csrf); ?>">
            <div class="col-auto">
                <label class="form-label"><?php echo __('year'); ?></label>
                <input type="number" name="period_year" class="form-control form-control-sm" value="<?php echo (int) $year; ?>" min="2000" max="2100">
            </div>
            <div class="col-auto">
                <label class="form-label"><?php echo __('month'); ?></label>
                <input type="number" name="period_month" class="form-control form-control-sm" value="<?php echo (int) $month; ?>" min="1" max="12">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary" <?php echo $schemaReady ? '' : 'disabled'; ?>><?php echo __('hr_r_build_wps'); ?></button>
            </div>
        </form>
        <?php if ($batches !== []) { ?>
        <div class="table-responsive">
            <table class="table rateb-table table-sm mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo __('period'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th class="text-end"><?php echo __('hr_r_ready'); ?></th>
                    <th class="text-end"><?php echo __('hr_r_exceptions'); ?></th>
                    <th><?php echo __('hr_r_external_sent'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($batches as $b) {
                    $bid = (int) ($b['id'] ?? 0);
                    ?>
                    <tr>
                        <td class="rateb-ltr-num"><a href="<?php echo rateb_url(rateb_app_route('hr/saudi-compliance/reports')) . '?type=wps&batch_id=' . $bid; ?>"><?php echo $bid; ?></a></td>
                        <td class="rateb-ltr-num"><?php echo sprintf('%04d-%02d', (int) ($b['period_year'] ?? 0), (int) ($b['period_month'] ?? 0)); ?></td>
                        <td><?php echo $escape((string) ($b['status'] ?? '')); ?></td>
                        <td class="text-end rateb-ltr-num"><?php echo (int) ($b['ready_count'] ?? 0); ?></td>
                        <td class="text-end rateb-ltr-num"><?php echo (int) ($b['exception_count'] ?? 0); ?></td>
                        <td class="rateb-ltr-num"><?php echo (int) ($b['external_sent'] ?? 0); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>
</div>
<?php } else { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('hr_r_update_employee'); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url(rateb_app_route('hr/saudi-compliance/employee')); ?>" class="row g-2">
            <input type="hidden" name="_token" value="<?php echo $escape($csrf); ?>">
            <div class="col-md-2"><label class="form-label"><?php echo __('employee_id'); ?></label><input type="number" name="employee_id" class="form-control form-control-sm" required min="1"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_nationality'); ?></label><input type="text" name="nationality_code" class="form-control form-control-sm" maxlength="8" placeholder="SA"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_classification'); ?></label>
                <select name="saudi_classification" class="form-select form-select-sm">
                    <option value=""><?php echo __('auto'); ?></option>
                    <option value="saudi"><?php echo __('hr_r_saudi'); ?></option>
                    <option value="non_saudi"><?php echo __('hr_r_non_saudi'); ?></option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_employment_type'); ?></label><input type="text" name="employment_type" class="form-control form-control-sm" maxlength="32"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_iqama'); ?></label><input type="text" name="iqama_number" class="form-control form-control-sm" maxlength="64"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_iqama_expiry'); ?></label><input type="date" name="iqama_expiry" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_gosi_number'); ?></label><input type="text" name="gosi_number" class="form-control form-control-sm" maxlength="64"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_iban'); ?></label><input type="text" name="wps_iban" class="form-control form-control-sm" maxlength="64"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_bank_code'); ?></label><input type="text" name="wps_bank_code" class="form-control form-control-sm" maxlength="32"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_bank_name'); ?></label><input type="text" name="bank_name" class="form-control form-control-sm" maxlength="120"></div>
            <?php if ($canViewSalary) { ?>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_housing'); ?></label><input type="number" step="0.01" name="housing_allowance" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_transport'); ?></label><input type="number" step="0.01" name="transport_allowance" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_other_allowances'); ?></label><input type="number" step="0.01" name="other_gosi_allowances" class="form-control form-control-sm"></div>
            <?php } ?>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_gosi_eligible'); ?></label>
                <select name="gosi_eligible" class="form-select form-select-sm">
                    <option value="1"><?php echo __('yes'); ?></option>
                    <option value="0"><?php echo __('no'); ?></option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label"><?php echo __('hr_r_mol_contract'); ?></label><input type="text" name="mol_contract_number" class="form-control form-control-sm" maxlength="64"></div>
            <div class="col-md-4"><label class="form-label"><?php echo __('notes'); ?></label><input type="text" name="saudi_notes" class="form-control form-control-sm" maxlength="2000"></div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary w-100"><?php echo __('save'); ?></button></div>
        </form>
    </div>
</div>

<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('hr_r_employee_profiles'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table table-sm mb-0">
                <thead>
                <tr>
                    <th><?php echo __('employee'); ?></th>
                    <th><?php echo __('hr_r_classification'); ?></th>
                    <th><?php echo __('national_id'); ?></th>
                    <th><?php echo __('hr_r_iban'); ?></th>
                    <?php if ($canViewSalary) { ?><th class="text-end"><?php echo __('hr_r_contribution_base'); ?></th><?php } ?>
                    <th><?php echo __('hr_r_issues'); ?></th>
                    <th>GOSI</th>
                    <th>WPS</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($profiles === []) { ?>
                    <tr><td colspan="8" class="text-muted p-3"><?php echo __('no_records'); ?></td></tr>
                <?php } ?>
                <?php foreach ($profiles as $p) {
                    $eid = (int) ($p['employee_id'] ?? 0);
                    $issues = array_merge(
                        is_array($p['issues'] ?? null) ? $p['issues'] : [],
                        is_array($p['gosi']['issues'] ?? null) ? $p['gosi']['issues'] : [],
                        is_array($p['wps']['issues'] ?? null) ? $p['wps']['issues'] : []
                    );
                    $issues = array_values(array_unique($issues));
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo rateb_url(rateb_app_route('hr/employees/' . $eid)); ?>"><?php echo $escape((string) ($p['name'] ?? '')); ?></a>
                            <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($p['employee_code'] ?? '')); ?></div>
                        </td>
                        <td><?php echo $escape((string) ($p['saudi_classification'] ?? '')); ?></td>
                        <td class="rateb-ltr-num"><?php echo $escape((string) ($p['national_id'] ?? '')); ?></td>
                        <td class="rateb-ltr-num small"><?php echo $escape((string) ($p['wps_iban'] ?? '')); ?></td>
                        <?php if ($canViewSalary) { ?>
                            <td class="text-end rateb-ltr-num"><?php echo $escape(number_format((float) ($p['gosi']['contribution_base'] ?? 0), 2)); ?></td>
                        <?php } ?>
                        <td class="small"><?php echo $escape(implode(', ', $issues)); ?></td>
                        <td><?php echo !empty($p['gosi']['exception']) ? __('hr_r_exception') : __('hr_r_ready'); ?></td>
                        <td><?php echo !empty($p['wps']['exception']) ? __('hr_r_exception') : __('hr_r_ready'); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>
