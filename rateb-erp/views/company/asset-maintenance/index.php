<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::assetMaintenanceFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('asset_maintenance')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('asset-maintenance')) { ?>
        <?php Rateb\App\Core\View::partial('workflow-form', [
            'formFields' => $formFields,
            'formAction' => rateb_app_url('asset-maintenance'),
            'csrf' => $csrf,
            'lookups' => $lookups,
        ]); ?>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'asset_name', 'label' => 'assets'],
                ['name' => 'maintenance_type', 'label' => 'maintenance_type'],
                ['name' => 'scheduled_date', 'label' => 'scheduled_date'],
                ['name' => 'cost', 'label' => 'cost'],
                ['name' => 'status', 'label' => 'status'],
            ],
        ]); ?>
    </div>
</div>
