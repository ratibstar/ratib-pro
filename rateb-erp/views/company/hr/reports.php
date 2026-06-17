<?php
/** @var array<int, array<string, mixed>> $attendance */
/** @var array<int, array<string, mixed>> $payroll */
$year = (int) ($year ?? date('Y'));
$month = (int) ($month ?? date('n'));
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'attendance-monthly']);
?>
<form method="get" class="row g-2 align-items-end mb-4">
    <div class="col-auto">
        <label class="form-label"><?php echo __('period_year'); ?></label>
        <input type="number" name="year" class="form-control rateb-form-control rateb-ltr-num" value="<?php echo $year; ?>" min="2020" max="2100">
    </div>
    <div class="col-auto">
        <label class="form-label"><?php echo __('period_month'); ?></label>
        <input type="number" name="month" class="form-control rateb-form-control rateb-ltr-num" value="<?php echo $month; ?>" min="1" max="12">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm"><?php echo __('filter'); ?></button>
    </div>
    <div class="col-auto">
        <?php Rateb\App\Core\View::partial('export-toolbar', [
            'exportRoute' => $exportRoute ?? '',
            'exportEnabled' => $exportEnabled ?? true,
            'inline' => true,
        ]); ?>
    </div>
</form>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('hr_attendance'); ?> — <?php echo $year; ?>/<?php echo $month; ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table mb-0">
                        <thead><tr>
                            <th><?php echo __('employee_code'); ?></th>
                            <th><?php echo __('name'); ?></th>
                            <th><?php echo __('present_days'); ?></th>
                            <th><?php echo __('absent_days'); ?></th>
                            <th><?php echo __('leave_days'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if (empty($attendance)) { ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                        <?php } else {
                            foreach ($attendance as $row) { ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['employee_code'] ?? '')); ?></td>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></td>
                            <td><?php echo (int) ($row['present_days'] ?? 0); ?></td>
                            <td><?php echo (int) ($row['absent_days'] ?? 0); ?></td>
                            <td><?php echo (int) ($row['leave_days'] ?? 0); ?></td>
                        </tr>
                        <?php }
                        } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('hr_payroll'); ?> — <?php echo $year; ?>/<?php echo $month; ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table mb-0">
                        <thead><tr>
                            <th><?php echo __('employee_code'); ?></th>
                            <th><?php echo __('name'); ?></th>
                            <th><?php echo __('net_salary'); ?></th>
                            <th><?php echo __('status'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if (empty($payroll)) { ?>
                        <tr><td colspan="4" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                        <?php } else {
                            foreach ($payroll as $row) { ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['employee_code'] ?? '')); ?></td>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></td>
                            <td class="rateb-ltr-num"><?php echo number_format((float) ($row['net_salary'] ?? 0), 2); ?></td>
                            <td><?php echo __((string) ($row['status'] ?? 'draft')); ?></td>
                        </tr>
                        <?php }
                        } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
