<?php
/** @var bool $companyCreateAgencyHint */
/** @var bool $companyCreateMaintenanceOn */
if (!empty($companyCreateAgencyHint)) { ?>
<div class="alert alert-info mb-3">
    <?php echo Rateb\App\Core\View::escape(__('company_create_agency_path_hint')); ?>
    <?php if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) { ?>
    <div class="small mt-2 mb-0">
        <?php echo Rateb\App\Core\View::escape(__('company_create_maintenance_how')); ?>
    </div>
    <?php } ?>
</div>
<?php } elseif (!empty($companyCreateMaintenanceOn)) { ?>
<div class="alert alert-warning mb-3">
    <?php echo Rateb\App\Core\View::escape(__('company_create_maintenance_on_hint')); ?>
</div>
<?php } ?>
<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
