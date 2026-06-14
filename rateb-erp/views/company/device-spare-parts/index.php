<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::deviceSparePartsFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('device_spare_parts')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('device-spare-parts')) { ?>
        <?php Rateb\App\Core\View::partial('workflow-form', [
            'formFields' => $formFields,
            'formAction' => rateb_app_url('device-spare-parts'),
            'csrf' => $csrf,
            'lookups' => $lookups,
        ]); ?>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'device_name', 'label' => 'medical_devices'],
                ['name' => 'part_name', 'label' => 'part_name'],
                ['name' => 'part_no', 'label' => 'part_no'],
                ['name' => 'quantity', 'label' => 'quantity'],
                ['name' => 'reorder_level', 'label' => 'reorder_level'],
            ],
        ]); ?>
    </div>
</div>
