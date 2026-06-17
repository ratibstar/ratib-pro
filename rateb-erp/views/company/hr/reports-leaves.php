<?php
/** @var array<int, array<string, mixed>> $rows */
$year = (int) ($year ?? date('Y'));
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'leave-report']);
?>
<form method="get" class="row g-2 align-items-end mb-4">
    <div class="col-auto">
        <label class="form-label"><?php echo __('period_year'); ?></label>
        <input type="number" name="year" class="form-control rateb-form-control rateb-ltr-num" value="<?php echo $year; ?>" min="2020" max="2100">
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
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('hr_leave_report'); ?> — <?php echo $year; ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead><tr>
                    <th><?php echo __('employee_code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('leave_type'); ?></th>
                    <th><?php echo __('days'); ?></th>
                    <th><?php echo __('approved'); ?></th>
                </tr></thead>
                <tbody>
                <?php if (empty($rows)) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    foreach ($rows as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['employee_code'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['employee_name'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['leave_type'] ?? '')); ?></td>
                    <td><?php echo number_format((float) ($row['total_days'] ?? 0), 1); ?></td>
                    <td><?php echo (int) ($row['approved_count'] ?? 0); ?></td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
