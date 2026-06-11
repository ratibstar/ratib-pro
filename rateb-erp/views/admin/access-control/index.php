<?php $s = $stats ?? []; ?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="rateb-widget">
            <div class="rateb-widget-value"><?php echo (int) ($s['users'] ?? 0); ?></div>
            <div class="rateb-widget-label"><?php echo __('users'); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-widget">
            <div class="rateb-widget-value"><?php echo (int) ($s['roles'] ?? 0); ?></div>
            <div class="rateb-widget-label"><?php echo __('roles'); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-widget">
            <div class="rateb-widget-value"><?php echo (int) ($s['permissions'] ?? 0); ?></div>
            <div class="rateb-widget-label"><?php echo __('permissions'); ?></div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h3 class="h6"><i class="fas fa-users me-2"></i><?php echo __('users'); ?></h3>
                <p class="text-muted small"><?php echo __('access_users_help'); ?></p>
                <a href="<?php echo rateb_url('admin/users'); ?>" class="btn btn-primary btn-sm"><?php echo __('manage'); ?></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h3 class="h6"><i class="fas fa-user-shield me-2"></i><?php echo __('roles'); ?></h3>
                <p class="text-muted small"><?php echo __('access_roles_help'); ?></p>
                <a href="<?php echo rateb_url('admin/roles'); ?>" class="btn btn-primary btn-sm"><?php echo __('manage'); ?></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h3 class="h6"><i class="fas fa-key me-2"></i><?php echo __('permissions'); ?></h3>
                <p class="text-muted small"><?php echo __('access_permissions_help'); ?></p>
                <a href="<?php echo rateb_url('admin/permissions'); ?>" class="btn btn-primary btn-sm"><?php echo __('manage'); ?></a>
            </div>
        </div>
    </div>
</div>
