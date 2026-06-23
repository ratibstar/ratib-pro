<?php
/** @var array<string, mixed> $vehicle */
/** @var array<string, mixed>|null $employee */
/** @var string $company_name */
/** @var string $assigned_employee_name */
/** @var string $department_name */
/** @var string $status_label */
/** @var string $routePrefix */
/** @var string $employeeRoutePrefix */
$vehicle = $vehicle ?? [];
$employee = $employee ?? null;
$routePrefix = $routePrefix ?? rateb_app_route('hr/fleet');
$employeeRoutePrefix = $employeeRoutePrefix ?? rateb_app_route('hr/employees');
$canManage = $canManage ?? true;
$vehicleId = (int) ($vehicle['id'] ?? 0);
$empId = (int) ($vehicle['assigned_employee_id'] ?? 0);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'fleet-manage']);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo __('hr_fleet'); ?> — <?php echo Rateb\App\Core\View::escape((string) ($vehicle['plate_number'] ?? '')); ?></span>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('back'); ?></a>
            <a href="<?php echo rateb_url($routePrefix . '/' . $vehicleId . '/print'); ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                <i class="fas fa-print"></i> <?php echo __('print'); ?>
            </a>
            <?php if ($empId > 0) { ?>
            <a href="<?php echo rateb_url($routePrefix . '/' . $vehicleId . '/receipt'); ?>" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener">
                <i class="fas fa-file-signature"></i> <?php echo __('fleet_employee_receipt'); ?>
            </a>
            <?php } ?>
            <?php if ($canManage) { ?>
            <a href="<?php echo rateb_url($routePrefix . '/' . $vehicleId . '/edit'); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
            </a>
            <?php } ?>
        </div>
    </div>
    <div class="rateb-card-body">
        <div class="row g-4">
            <div class="col-lg-6">
                <h6 class="mb-3"><?php echo __('fleet_vehicle_details'); ?></h6>
                <dl class="row mb-0">
                    <?php if ($company_name !== '') { ?>
                    <dt class="col-sm-4"><?php echo __('companies'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape($company_name); ?></dd>
                    <?php } ?>
                    <dt class="col-sm-4"><?php echo __('plate_number'); ?></dt>
                    <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($vehicle['plate_number'] ?? '')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('brand'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($vehicle['brand'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('model'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($vehicle['model'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('model_year'); ?></dt>
                    <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($vehicle['model_year'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('status'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape($status_label); ?></dd>
                    <dt class="col-sm-4"><?php echo __('notes'); ?></dt>
                    <dd class="col-sm-8"><?php echo nl2br(Rateb\App\Core\View::escape((string) ($vehicle['notes'] ?? '—'))); ?></dd>
                </dl>
            </div>
            <div class="col-lg-6">
                <h6 class="mb-3"><?php echo __('fleet_employee_details'); ?></h6>
                <?php if ($employee === null || $empId < 1) { ?>
                <p class="text-muted"><?php echo __('fleet_no_assigned_employee'); ?></p>
                <?php } else { ?>
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?php echo __('name'); ?></dt>
                    <dd class="col-sm-8">
                        <a href="<?php echo rateb_url($employeeRoutePrefix . '/' . $empId); ?>"><?php echo Rateb\App\Core\View::escape((string) ($employee['name'] ?? $assigned_employee_name)); ?></a>
                    </dd>
                    <dt class="col-sm-4"><?php echo __('employee_code'); ?></dt>
                    <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['employee_code'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('national_id'); ?></dt>
                    <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['national_id'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('email'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($employee['email'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('phone'); ?></dt>
                    <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($employee['phone'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('department'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape($department_name !== '' ? $department_name : '—'); ?></dd>
                    <dt class="col-sm-4"><?php echo __('job_title'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($employee['job_title'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('hire_date'); ?></dt>
                    <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($employee['hire_date'] ?? '—')); ?></dd>
                    <dt class="col-sm-4"><?php echo __('status'); ?></dt>
                    <dd class="col-sm-8"><?php echo __((string) ($employee['status'] ?? 'active')); ?></dd>
                </dl>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
