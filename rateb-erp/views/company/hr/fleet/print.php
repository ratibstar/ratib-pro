<?php
/** @var array<string, mixed> $vehicle */
/** @var array<string, mixed>|null $employee */
/** @var string $company_name */
/** @var string $assigned_employee_name */
/** @var string $department_name */
/** @var string $status_label */
$vehicle = $vehicle ?? [];
$employee = $employee ?? null;
?>
<div class="rateb-po-print-header">
    <h1 class="h4 mb-1"><?php echo __('hr_fleet'); ?></h1>
    <?php if ($company_name !== '') { ?>
    <div class="text-muted small"><?php echo Rateb\App\Core\View::escape($company_name); ?></div>
    <?php } ?>
</div>
<table class="table table-bordered table-sm mb-3">
    <tbody>
    <tr><th style="width:32%"><?php echo __('plate_number'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($vehicle['plate_number'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('brand'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($vehicle['brand'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('model'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($vehicle['model'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('model_year'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($vehicle['model_year'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('assigned_employee'); ?></th><td><?php echo Rateb\App\Core\View::escape($assigned_employee_name !== '' ? $assigned_employee_name : '—'); ?></td></tr>
    <?php if ($employee !== null) { ?>
    <tr><th><?php echo __('employee_code'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['employee_code'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('department'); ?></th><td><?php echo Rateb\App\Core\View::escape($department_name !== '' ? $department_name : '—'); ?></td></tr>
    <tr><th><?php echo __('job_title'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($employee['job_title'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('phone'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['phone'] ?? '—')); ?></td></tr>
    <?php } ?>
    <tr><th><?php echo __('status'); ?></th><td><?php echo Rateb\App\Core\View::escape($status_label); ?></td></tr>
    </tbody>
</table>
<?php if (trim((string) ($vehicle['notes'] ?? '')) !== '') { ?>
<p class="mb-0"><strong><?php echo __('notes'); ?>:</strong><br><?php echo nl2br(Rateb\App\Core\View::escape((string) $vehicle['notes'])); ?></p>
<?php } ?>
