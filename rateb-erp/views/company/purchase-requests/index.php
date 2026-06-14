<?php Rateb\App\Core\View::partial('export-toolbar', [
    'exportRoute' => $exportRoute ?? rateb_app_url('purchase-requests/export'),
    'exportEnabled' => $exportEnabled ?? rateb_can_export_entity('purchase-requests'),
]); ?>
<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
