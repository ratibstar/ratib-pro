<?php
/** @var array<string, mixed> $period */
/** @var array<string, mixed> $line */
$period = $period ?? [];
$line = $line ?? [];
?>
<div class="text-center mb-4">
    <h2><?php echo __('payslip'); ?></h2>
    <p class="mb-0"><?php echo (int) ($period['period_year'] ?? 0); ?> / <?php echo (int) ($period['period_month'] ?? 0); ?></p>
</div>
<table class="table table-bordered w-100 mb-4">
    <tr><th><?php echo __('employee_code'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($line['employee_code'] ?? '')); ?></td></tr>
    <tr><th><?php echo __('name'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($line['employee_name'] ?? '')); ?></td></tr>
    <tr><th><?php echo __('job_title'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($line['job_title'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('national_id'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($line['national_id'] ?? '—')); ?></td></tr>
</table>
<table class="table table-bordered w-100">
    <thead><tr>
        <th><?php echo __('description'); ?></th>
        <th class="text-end"><?php echo __('amount'); ?></th>
    </tr></thead>
    <tbody>
    <tr><td><?php echo __('basic_salary'); ?></td><td class="text-end rateb-ltr-num"><?php echo number_format((float) ($line['basic_salary'] ?? 0), 2); ?></td></tr>
    <tr><td><?php echo __('allowances'); ?></td><td class="text-end rateb-ltr-num"><?php echo number_format((float) ($line['allowances'] ?? 0), 2); ?></td></tr>
    <tr><td><?php echo __('deductions'); ?></td><td class="text-end rateb-ltr-num"><?php echo number_format((float) ($line['deductions'] ?? 0), 2); ?></td></tr>
    <tr class="fw-bold"><td><?php echo __('net_salary'); ?></td><td class="text-end rateb-ltr-num"><?php echo number_format((float) ($line['net_salary'] ?? 0), 2); ?></td></tr>
    </tbody>
</table>
<?php if (!empty($line['notes'])) { ?>
<p class="small text-muted mt-3"><?php echo Rateb\App\Core\View::escape((string) $line['notes']); ?></p>
<?php } ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
