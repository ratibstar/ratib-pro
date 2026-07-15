<?php
/** @var bool $companyCreateAgencyHint */
/** @var string $controlPanelAgenciesUrl */
if (!empty($companyCreateAgencyHint)) { ?>
<div class="alert alert-info mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><?php echo Rateb\App\Core\View::escape(__('company_create_agency_path_hint')); ?></div>
    <?php if (!empty($controlPanelAgenciesUrl)) { ?>
    <a class="btn btn-sm btn-primary" href="<?php echo Rateb\App\Core\View::escape($controlPanelAgenciesUrl); ?>" target="_blank" rel="noopener">
        <i class="fas fa-external-link-alt"></i> <?php echo __('company_open_control_agencies'); ?>
    </a>
    <?php } ?>
</div>
<?php } ?>
<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
