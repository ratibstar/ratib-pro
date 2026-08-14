<?php
/** @var int $companyId */
/** @var string $type */
/** @var list<array<string,mixed>> $rows */
/** @var int $year */
/** @var int $month */
/** @var int $batchId */
/** @var bool $canViewSalary */
/** @var string $exportRoute */
/** @var bool $exportEnabled */
/** @var string $routePrefix */
/** @var list<array<string,mixed>> $batches */
$companyId = (int) ($companyId ?? 0);
$type = (string) ($type ?? 'missing');
$rows = $rows ?? [];
$year = (int) ($year ?? date('Y'));
$month = (int) ($month ?? date('n'));
$batchId = (int) ($batchId ?? 0);
$canViewSalary = (bool) ($canViewSalary ?? false);
$exportRoute = (string) ($exportRoute ?? '');
$exportEnabled = (bool) ($exportEnabled ?? true);
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/saudi-compliance'));
$batches = $batches ?? [];
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
$types = [
    'missing' => __('hr_r_report_missing'),
    'gosi' => __('hr_r_report_gosi'),
    'wps' => __('hr_r_report_wps'),
    'reconciliation' => __('hr_r_report_reconciliation'),
];
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'saudi-reports']);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/hr-module.css'); ?>">

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?php echo __('hr_saudi_reports'); ?></h1>
    <a href="<?php echo rateb_url(rateb_app_route('hr/saudi-compliance')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_saudi_compliance'); ?></a>
</div>

<form method="get" action="<?php echo rateb_url(rateb_app_route('hr/saudi-compliance/reports')); ?>" class="rateb-card mb-3">
    <div class="rateb-card-body row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label"><?php echo __('type'); ?></label>
            <select name="type" class="form-select form-select-sm">
                <?php foreach ($types as $k => $label) {
                    $sel = $type === $k ? ' selected' : '';
                    echo '<option value="' . $escape($k) . '"' . $sel . '>' . $escape($label) . '</option>';
                } ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo __('year'); ?></label>
            <input type="number" name="period_year" class="form-control form-control-sm" value="<?php echo $year; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo __('month'); ?></label>
            <input type="number" name="period_month" class="form-control form-control-sm" value="<?php echo $month; ?>" min="1" max="12">
        </div>
        <div class="col-md-3">
            <label class="form-label"><?php echo __('hr_r_wps_batch'); ?></label>
            <select name="batch_id" class="form-select form-select-sm">
                <option value="0"><?php echo __('all'); ?></option>
                <?php foreach ($batches as $b) {
                    $bid = (int) ($b['id'] ?? 0);
                    $sel = $batchId === $bid ? ' selected' : '';
                    $label = sprintf('#%d %04d-%02d', $bid, (int) ($b['period_year'] ?? 0), (int) ($b['period_month'] ?? 0));
                    echo '<option value="' . $bid . '"' . $sel . '>' . $escape($label) . '</option>';
                } ?>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-primary w-100"><?php echo __('filter'); ?></button>
        </div>
        <div class="col-md-1">
            <?php Rateb\App\Core\View::partial('export-toolbar', [
                'exportRoute' => $exportRoute,
                'exportEnabled' => $exportEnabled,
            ]); ?>
        </div>
    </div>
</form>

<?php if (($type === 'gosi' || $type === 'reconciliation') && !$canViewSalary) { ?>
<div class="alert alert-info"><?php echo __('hr_r_salary_privacy'); ?></div>
<?php } ?>

<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table table-sm mb-0">
                <thead>
                <tr>
                    <?php if ($type === 'gosi') { ?>
                        <th><?php echo __('employee'); ?></th>
                        <th><?php echo __('hr_r_classification'); ?></th>
                        <th class="text-end"><?php echo __('hr_r_contribution_base'); ?></th>
                        <th class="text-end"><?php echo __('hr_r_employee_amount'); ?></th>
                        <th class="text-end"><?php echo __('hr_r_employer_amount'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('hr_r_external_sent'); ?></th>
                    <?php } elseif ($type === 'wps') { ?>
                        <th><?php echo __('employee'); ?></th>
                        <th><?php echo __('national_id'); ?></th>
                        <th><?php echo __('hr_r_iban'); ?></th>
                        <th><?php echo __('hr_r_bank_code'); ?></th>
                        <?php if ($canViewSalary) { ?><th class="text-end"><?php echo __('net'); ?></th><?php } ?>
                        <th><?php echo __('hr_r_ready'); ?></th>
                        <th><?php echo __('notes'); ?></th>
                        <th><?php echo __('hr_r_external_sent'); ?></th>
                    <?php } elseif ($type === 'reconciliation') { ?>
                        <th><?php echo __('employee'); ?></th>
                        <th class="text-end"><?php echo __('hr_r_payroll_gross'); ?></th>
                        <th class="text-end"><?php echo __('hr_r_contribution_base'); ?></th>
                        <th class="text-end"><?php echo __('hr_r_delta'); ?></th>
                        <th class="text-end"><?php echo __('net'); ?></th>
                        <th><?php echo __('status'); ?></th>
                    <?php } else { ?>
                        <th><?php echo __('employee'); ?></th>
                        <th><?php echo __('national_id'); ?></th>
                        <th><?php echo __('hr_r_classification'); ?></th>
                        <th><?php echo __('hr_r_issues'); ?></th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) { ?>
                    <tr><td colspan="8" class="text-muted p-3"><?php echo __('no_records'); ?></td></tr>
                <?php } ?>
                <?php foreach ($rows as $r) { ?>
                    <tr>
                        <?php if ($type === 'gosi') { ?>
                            <td><?php echo $escape((string) ($r['employee_name'] ?? '')); ?>
                                <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($r['employee_code'] ?? '')); ?></div></td>
                            <td><?php echo $escape((string) ($r['saudi_classification'] ?? '')); ?></td>
                            <td class="text-end rateb-ltr-num"><?php echo $escape(number_format((float) ($r['contribution_base'] ?? 0), 2)); ?></td>
                            <td class="text-end rateb-ltr-num"><?php echo $escape(number_format((float) ($r['employee_amount'] ?? 0), 2)); ?></td>
                            <td class="text-end rateb-ltr-num"><?php echo $escape(number_format((float) ($r['employer_amount'] ?? 0), 2)); ?></td>
                            <td><?php echo $escape((string) ($r['validation_status'] ?? '')); ?></td>
                            <td class="rateb-ltr-num"><?php echo (int) ($r['external_sent'] ?? 0); ?></td>
                        <?php } elseif ($type === 'wps') { ?>
                            <td><?php echo $escape((string) ($r['employee_name'] ?? '')); ?>
                                <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($r['employee_code'] ?? '')); ?></div></td>
                            <td class="rateb-ltr-num"><?php echo $escape((string) ($r['national_id'] ?? '')); ?></td>
                            <td class="rateb-ltr-num small"><?php echo $escape((string) ($r['iban'] ?? '')); ?></td>
                            <td class="rateb-ltr-num"><?php echo $escape((string) ($r['bank_code'] ?? '')); ?></td>
                            <?php if ($canViewSalary) { ?>
                                <td class="text-end rateb-ltr-num"><?php echo $escape(number_format((float) ($r['net_salary'] ?? 0), 2)); ?></td>
                            <?php } ?>
                            <td><?php echo !empty($r['ready']) ? __('yes') : __('no'); ?></td>
                            <td class="small"><?php echo $escape((string) ($r['validation_notes'] ?? '')); ?></td>
                            <td class="rateb-ltr-num"><?php echo (int) ($r['external_sent'] ?? 0); ?></td>
                        <?php } elseif ($type === 'reconciliation') { ?>
                            <td><?php echo $escape((string) ($r['employee_name'] ?? '')); ?>
                                <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($r['employee_code'] ?? '')); ?></div></td>
                            <td class="text-end rateb-ltr-num"><?php echo $escape(number_format((float) ($r['payroll_gross'] ?? 0), 2)); ?></td>
                            <td class="text-end rateb-ltr-num"><?php echo $escape(number_format((float) ($r['gosi_base'] ?? 0), 2)); ?></td>
                            <td class="text-end rateb-ltr-num"><?php echo $escape(number_format((float) ($r['delta'] ?? 0), 2)); ?></td>
                            <td class="text-end rateb-ltr-num"><?php echo $escape(number_format((float) ($r['net_salary'] ?? 0), 2)); ?></td>
                            <td><?php echo $escape((string) ($r['validation_status'] ?? '')); ?></td>
                        <?php } else { ?>
                            <td><?php echo $escape((string) ($r['name'] ?? '')); ?>
                                <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($r['employee_code'] ?? '')); ?></div></td>
                            <td class="rateb-ltr-num"><?php echo $escape((string) ($r['national_id'] ?? '')); ?></td>
                            <td><?php echo $escape((string) ($r['saudi_classification'] ?? '')); ?></td>
                            <td class="small"><?php echo $escape(is_array($r['issues'] ?? null) ? implode(', ', $r['issues']) : (string) ($r['issues'] ?? '')); ?></td>
                        <?php } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<p class="text-muted small mt-2 mb-0"><?php echo __('hr_saudi_no_external_send'); ?></p>
