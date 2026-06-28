<?php Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'permission-requests']); ?>
<?php if (!empty($createEnabled)) { ?>
<div class="mb-3">
    <a href="<?php echo rateb_url($routePrefix . '/create'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> <?php echo __('create'); ?>
    </a>
</div>
<?php } ?>
<?php Rateb\App\Core\View::partial('crud-index', array_merge(get_defined_vars(), ['title' => '', 'createEnabled' => false])); ?>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
