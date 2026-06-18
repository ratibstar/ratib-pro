<?php Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'attendance-daily']); ?>
<?php
$employeeOptions = $lookups['employees'] ?? [];
if ($employeeOptions === []) {
    ?>
<div class="alert alert-warning mb-3">
    <?php echo __('hr_attendance_need_employee'); ?>
    <a class="alert-link ms-1" href="<?php echo rateb_url_with_ops_company(rateb_app_route('hr/employees/create')); ?>">
        <?php echo __('create'); ?> <?php echo __('hr_employees'); ?>
    </a>
</div>
    <?php
}
?>
<?php Rateb\App\Core\View::partial('crud-form', get_defined_vars()); ?>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
