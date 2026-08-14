<?php
/** @var array<string, mixed> $period */
/** @var array<int, array<string, mixed>> $lines */
$period = $period ?? [];
$lines = $lines ?? [];
$canManage = $canManage ?? true;
$status = (string) ($period['status'] ?? 'draft');
$periodId = (int) ($period['id'] ?? 0);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'payroll-list']);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <?php echo __('hr_payroll'); ?> —
            <?php echo (int) ($period['period_year'] ?? 0); ?>/<?php echo (int) ($period['period_month'] ?? 0); ?>
            <span class="badge bg-secondary ms-2"><?php echo __($status); ?></span>
        </span>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('back'); ?></a>
            <?php if ($canManage && $status === 'draft') { ?>
            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $periodId . '/generate'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-calculator"></i> <?php echo __('generate_payroll'); ?></button>
            </form>
            <?php if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) { ?>
            <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-gavel"></i> <?php echo __('approvals_open_oversight'); ?>
            </a>
            <?php } else { ?>
            <span class="badge bg-warning text-dark align-self-center"><?php echo __('awaiting_oversight_approval'); ?></span>
            <?php } ?>
            <?php } elseif ($canManage && $status === 'approved') { ?>
            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $periodId . '/post'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-lock"></i> <?php echo __('post_payroll'); ?></button>
            </form>
            <?php } ?>
            <?php Rateb\App\Core\View::partial('export-toolbar', [
                'exportRoute' => rateb_url($routePrefix . '/' . $periodId . '/export'),
                'exportEnabled' => true,
                'inline' => true,
            ]); ?>
        </div>
    </div>
    <?php if ($status === 'posted' || $status === 'approved') { ?>
    <div class="alert alert-info mb-0 rounded-0 border-0 border-bottom small">
        <i class="fas fa-info-circle"></i>
        <?php echo __('payroll_posted_status_note'); ?>
    </div>
    <?php } ?>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('employee_code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('basic_salary'); ?></th>
                    <th><?php echo __('allowances'); ?></th>
                    <th><?php echo __('deductions'); ?></th>
                    <th><?php echo __('net_salary'); ?></th>
                    <th><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($lines === []) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?php echo __('payroll_no_lines'); ?></td></tr>
                <?php } else {
                    $totalNet = 0.0;
                    foreach ($lines as $line) {
                        $net = (float) ($line['net_salary'] ?? 0);
                        $totalNet += $net;
                        ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($line['employee_code'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($line['employee_name'] ?? '')); ?></td>
                    <td class="rateb-ltr-num"><?php echo number_format((float) ($line['basic_salary'] ?? 0), 2); ?></td>
                    <td class="rateb-ltr-num"><?php echo number_format((float) ($line['allowances'] ?? 0), 2); ?></td>
                    <td class="rateb-ltr-num"><?php echo number_format((float) ($line['deductions'] ?? 0), 2); ?></td>
                    <td class="rateb-ltr-num"><?php echo number_format($net, 2); ?></td>
                    <td>
                        <a href="<?php echo rateb_url($routePrefix . '/' . $periodId . '/payslip/' . (int) ($line['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                            <i class="fas fa-print"></i> <?php echo __('payslip'); ?>
                        </a>
                    </td>
                </tr>
                <?php } ?>
                <tr class="table-light fw-bold">
                    <td colspan="6" class="text-end"><?php echo __('total'); ?></td>
                    <td class="rateb-ltr-num"><?php echo number_format($totalNet, 2); ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
