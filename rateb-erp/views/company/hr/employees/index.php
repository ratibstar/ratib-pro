<?php Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employees']); ?>
<div class="mb-3">
    <a href="<?php echo rateb_app_url('hr/departments'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-sitemap"></i> <?php echo __('hr_departments'); ?>
    </a>
</div>
<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
