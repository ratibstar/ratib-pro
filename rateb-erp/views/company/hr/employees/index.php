<?php Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employees']); ?>
<div class="mb-3 d-flex flex-wrap gap-2">
    <a href="<?php echo rateb_app_url('hr/departments'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-sitemap"></i> <?php echo __('hr_departments'); ?>
    </a>
    <?php if ($exportEnabled ?? true) { ?>
    <?php Rateb\App\Core\View::partial('export-toolbar', [
        'exportRoute' => rateb_app_url('hr/employees/export'),
        'exportEnabled' => $exportEnabled ?? true,
        'inline' => true,
    ]); ?>
    <?php } ?>
</div>
<?php Rateb\App\Core\View::partial('crud-index', array_merge(get_defined_vars(), ['viewEnabled' => true])); ?>
