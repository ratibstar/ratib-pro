<?php Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employees']); ?>
<?php Rateb\App\Core\View::partial('crud-index', array_merge(get_defined_vars(), [
    'viewEnabled' => true,
    'exportRoute' => rateb_app_url('hr/employees/export'),
])); ?>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
