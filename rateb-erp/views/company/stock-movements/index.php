<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::stockMovementFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('stock_movements')); ?></div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('export-toolbar', [
            'exportRoute' => rateb_app_url('stock-movements/export'),
            'exportEnabled' => $exportEnabled ?? true,
        ]); ?>
        <?php if ($canManage ?? rateb_can_manage_entity('stock-movements')) { ?>
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
                ['name' => 'movement_no', 'label' => 'movement_no'],
                ['name' => 'movement_type', 'label' => 'movement_type'],
                ['name' => 'item_name', 'label' => 'item_name'],
                ['name' => 'quantity', 'label' => 'quantity'],
                ['name' => 'warehouse_name', 'label' => 'warehouses'],
                ['name' => 'created_at', 'label' => 'created_at'],
            ],
            'routePrefix' => rateb_app_route('stock-movements'),
            'canManage' => false,
            'canDelete' => $canManage ?? rateb_can_manage_entity('stock-movements'),
            'bulkDeleteRoute' => rateb_app_url('stock-movements/bulk-delete'),
            'csrf' => $csrf,
        ]); ?>
    </div>
</div>
