<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<?php Rateb\App\Core\View::partial('export-toolbar', [
    'exportRoute' => $exportRoute ?? rateb_app_url('customs-clearance-costs/export'),
    'exportEnabled' => $exportEnabled ?? rateb_can_export_entity('customs-clearance-costs'),
]); ?>
<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
