<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::assetAssignmentFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('asset_assignments')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('asset-assignments')) { ?>
        <?php Rateb\App\Core\View::partial('workflow-form', [
            'formFields' => $formFields,
            'formAction' => rateb_app_url('asset-assignments'),
            'csrf' => $csrf,
            'lookups' => $lookups,
        ]); ?>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'asset_name', 'label' => 'assets'],
                ['name' => 'assigned_to', 'label' => 'assigned_to'],
                ['name' => 'department', 'label' => 'department'],
                ['name' => 'assigned_at', 'label' => 'assigned_at'],
            ],
        ]); ?>
    </div>
</div>
