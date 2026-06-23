<?php Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'fleet-manage']); ?>
<?php Rateb\App\Core\View::partial('crud-index', array_merge(get_defined_vars(), [
    'viewEnabled' => true,
    'printEnabled' => true,
    'employeeReceiptEnabled' => true,
])); ?>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
