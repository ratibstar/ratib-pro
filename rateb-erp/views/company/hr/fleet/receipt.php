<?php
/** @var array<string, mixed> $vehicle */
/** @var array<string, mixed>|null $employee */
/** @var string $company_name */
/** @var string $assigned_employee_name */
/** @var string $department_name */
/** @var string $receipt_date */
$vehicle = $vehicle ?? [];
$employee = $employee ?? null;
$receipt_date = (string) ($receipt_date ?? date('Y-m-d'));
?>
<div class="rateb-po-print-header text-center">
    <h1 class="h4 mb-1"><?php echo __('fleet_employee_receipt'); ?></h1>
    <?php if ($company_name !== '') { ?>
    <div class="fw-semibold"><?php echo Rateb\App\Core\View::escape($company_name); ?></div>
    <?php } ?>
    <div class="text-muted small mt-1"><?php echo __('date'); ?>: <?php echo Rateb\App\Core\View::escape($receipt_date); ?></div>
</div>
<?php if ($employee === null) { ?>
<p class="text-muted"><?php echo __('fleet_no_assigned_employee'); ?></p>
<?php } else { ?>
<p class="mb-4"><?php echo __('fleet_receipt_ack'); ?></p>
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <h6 class="border-bottom pb-2"><?php echo __('fleet_vehicle_details'); ?></h6>
        <table class="table table-bordered table-sm mb-0">
            <tbody>
            <tr><th style="width:40%"><?php echo __('plate_number'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($vehicle['plate_number'] ?? '—')); ?></td></tr>
            <tr><th><?php echo __('brand'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($vehicle['brand'] ?? '—')); ?></td></tr>
            <tr><th><?php echo __('model'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($vehicle['model'] ?? '—')); ?></td></tr>
            <tr><th><?php echo __('model_year'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($vehicle['model_year'] ?? '—')); ?></td></tr>
            <tr><th><?php echo __('status'); ?></th><td><?php echo Rateb\App\Core\View::escape(__((string) ($vehicle['status'] ?? 'active'))); ?></td></tr>
            </tbody>
        </table>
    </div>
    <div class="col-md-6">
        <h6 class="border-bottom pb-2"><?php echo __('fleet_employee_details'); ?></h6>
        <table class="table table-bordered table-sm mb-0">
            <tbody>
            <tr><th style="width:40%"><?php echo __('name'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($employee['name'] ?? $assigned_employee_name)); ?></td></tr>
            <tr><th><?php echo __('employee_code'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['employee_code'] ?? '—')); ?></td></tr>
            <tr><th><?php echo __('national_id'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['national_id'] ?? '—')); ?></td></tr>
            <tr><th><?php echo __('department'); ?></th><td><?php echo Rateb\App\Core\View::escape($department_name !== '' ? $department_name : '—'); ?></td></tr>
            <tr><th><?php echo __('job_title'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($employee['job_title'] ?? '—')); ?></td></tr>
            <tr><th><?php echo __('phone'); ?></th><td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['phone'] ?? '—')); ?></td></tr>
            <tr><th><?php echo __('email'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($employee['email'] ?? '—')); ?></td></tr>
            <tr><th><?php echo __('hire_date'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($employee['hire_date'] ?? '—')); ?></td></tr>
            </tbody>
        </table>
    </div>
</div>
<?php if (trim((string) ($vehicle['notes'] ?? '')) !== '') { ?>
<p class="mb-4"><strong><?php echo __('notes'); ?>:</strong> <?php echo nl2br(Rateb\App\Core\View::escape((string) $vehicle['notes'])); ?></p>
<?php } ?>
<div class="row g-4 mt-5">
    <div class="col-md-6 text-center">
        <div class="border-top pt-2 mt-5"><?php echo __('employee_signature'); ?></div>
        <div class="small text-muted mt-1"><?php echo Rateb\App\Core\View::escape((string) ($employee['name'] ?? '')); ?></div>
    </div>
    <div class="col-md-6 text-center">
        <div class="border-top pt-2 mt-5"><?php echo __('company_representative'); ?></div>
        <div class="small text-muted mt-1"><?php echo Rateb\App\Core\View::escape($company_name); ?></div>
    </div>
</div>
<?php } ?>
