<?php if (!rateb_is_super_admin()) {
    return;
} ?>
<div class="alert alert-info border-info mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
    <div>
        <i class="fas fa-circle-info me-1"></i>
        <strong><?php echo __('admin_oversight_title'); ?></strong>
        <?php echo __('admin_oversight_hint'); ?>
    </div>
    <a href="<?php echo rateb_url(rateb_app_route('purchase-requests')); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-file-circle-plus"></i> <?php echo __('open_operations'); ?>
    </a>
</div>
