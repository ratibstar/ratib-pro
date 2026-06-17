<?php Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employees']); ?>
<?php if ($exportEnabled ?? true) { ?>
<div class="mb-3">
    <?php Rateb\App\Core\View::partial('export-toolbar', [
        'exportRoute' => rateb_app_url('hr/employees/export'),
        'exportEnabled' => $exportEnabled ?? true,
        'inline' => true,
    ]); ?>
</div>
<?php } ?>
<?php Rateb\App\Core\View::partial('crud-index', array_merge(get_defined_vars(), ['viewEnabled' => true])); ?>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
