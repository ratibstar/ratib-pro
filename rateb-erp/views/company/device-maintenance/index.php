<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::deviceMaintenanceFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('device_maintenance')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('device-maintenance')) { ?>
        <?php Rateb\App\Core\View::partial('workflow-form', [
            'formFields' => $formFields,
            'formAction' => rateb_app_url('device-maintenance'),
            'csrf' => $csrf,
            'lookups' => $lookups,
        ]); ?>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'device_name', 'label' => 'medical_devices'],
                ['name' => 'service_date', 'label' => 'service_date'],
                ['name' => 'service_type', 'label' => 'service_type'],
                ['name' => 'provider', 'label' => 'provider'],
                ['name' => 'cost', 'label' => 'cost'],
            ],
        ]); ?>
    </div>
</div>
