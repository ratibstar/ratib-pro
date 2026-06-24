<?php if (!rateb_is_super_admin()) {
    return;
} ?>
<div class="alert alert-primary border-primary mb-3" role="status">
    <i class="fas fa-check-double me-1"></i>
    <strong><?php echo __('approvals_oversight'); ?></strong>
    — <?php echo __('admin_oversight_approvals_hint'); ?>
</div>
