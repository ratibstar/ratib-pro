<?php
/** @var array<int, array<string, mixed>> $leaveBalances */
$leaveBalances = $leaveBalances ?? [];
$balanceYear = (int) ($balanceYear ?? date('Y'));
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'leave-balances']);
?>
<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label class="form-label"><?php echo __('period_year'); ?></label>
        <input type="number" name="year" class="form-control rateb-form-control rateb-ltr-num" value="<?php echo $balanceYear; ?>" min="2020" max="2100">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm"><?php echo __('filter'); ?></button>
    </div>
</form>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('hr_leave_balances'); ?> — <?php echo $balanceYear; ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0 table-sm">
                <thead><tr>
                    <th><?php echo __('employee_code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('leave_type'); ?></th>
                    <th><?php echo __('entitled_days'); ?></th>
                    <th><?php echo __('used_days'); ?></th>
                    <th><?php echo __('remaining_days'); ?></th>
                </tr></thead>
                <tbody>
                <?php if ($leaveBalances === []) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    foreach ($leaveBalances as $bal) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($bal['employee_code'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($bal['employee_name'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($bal['leave_type_name'] ?? '')); ?></td>
                    <td><?php echo number_format((float) ($bal['entitled_days'] ?? 0), 1); ?></td>
                    <td><?php echo number_format((float) ($bal['used_days'] ?? 0), 1); ?></td>
                    <td><?php echo number_format((float) ($bal['entitled_days'] ?? 0) - (float) ($bal['used_days'] ?? 0), 1); ?></td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
