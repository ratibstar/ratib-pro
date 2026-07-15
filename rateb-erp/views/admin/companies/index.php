<?php
/** @var bool $companyCreateAgencyHint */
if (!empty($companyCreateAgencyHint)) { ?>
<div class="alert alert-info mb-3">
    <?php echo Rateb\App\Core\View::escape(__('company_create_agency_path_hint')); ?>
</div>
<?php } ?>
<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
