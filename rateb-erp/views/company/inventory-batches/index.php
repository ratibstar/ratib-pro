<?php Rateb\App\Core\View::partial('export-toolbar', [
    'exportRoute' => $exportRoute ?? rateb_app_url('inventory-batches/export'),
    'exportEnabled' => $exportEnabled ?? true,
]); ?>
<?php Rateb\App\Core\View::partial('crud-index', array_merge(get_defined_vars(), [
    'title' => $title ?? __('inventory_batches'),
    'createEnabled' => $createEnabled ?? true,
    'exportEnabled' => false,
])); ?>
