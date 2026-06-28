<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::stockMovementFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
$canManage = $canManage ?? rateb_can_manage_entity('stock-movements');
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? __('stock_movements')); ?></span>
    </div>
    <div class="rateb-card-body">
        <?php if ($canManage) { ?>
        <?php Rateb\App\Core\View::partial('workflow-form', [
            'formFields' => $formFields,
            'formAction' => rateb_app_url('stock-movements'),
            'csrf' => $csrf,
            'lookups' => $lookups,
        ]); ?>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('crud-index', [
            'title' => '',
            'items' => $items ?? [],
            'fields' => [
                ['name' => 'movement_no', 'label' => 'movement_no', 'type' => 'id'],
                ['name' => 'movement_type', 'label' => 'movement_type'],
                ['name' => 'item_name', 'label' => 'item_name'],
                ['name' => 'quantity', 'label' => 'quantity'],
                ['name' => 'warehouse_name', 'label' => 'warehouses'],
                ['name' => 'created_at', 'label' => 'created_at'],
            ],
            'routePrefix' => rateb_app_route('stock-movements'),
            'permissionResource' => 'stock-movements',
            'bulkEnabled' => true,
            'createEnabled' => false,
            'actionsEnabled' => false,
            'exportEnabled' => $exportEnabled ?? true,
            'exportRoute' => rateb_app_url('stock-movements/export'),
            'csrf' => $csrf,
        ]); ?>
    </div>
</div>
