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
                <a href="<?php echo rateb_app_url('users'); ?>" class="btn btn-primary btn-sm"><?php echo __('manage'); ?></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h3 class="h6"><i class="fas fa-user-shield me-2"></i><?php echo __('roles'); ?></h3>
                <p class="text-muted small"><?php echo __('access_roles_help'); ?></p>
                <a href="<?php echo rateb_app_url('roles'); ?>" class="btn btn-primary btn-sm"><?php echo __('manage'); ?></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h3 class="h6"><i class="fas fa-key me-2"></i><?php echo __('permissions'); ?></h3>
                <p class="text-muted small"><?php echo __('access_permissions_help'); ?></p>
                <a href="<?php echo rateb_app_url('permissions'); ?>" class="btn btn-primary btn-sm"><?php echo __('manage'); ?></a>
            </div>
        </div>
    </div>
</div>
<div class="rateb-card mt-3">
    <div class="rateb-card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="h6 mb-1"><i class="fas fa-table-cells me-2"></i><?php echo __('permission_matrix'); ?></h3>
            <p class="text-muted small mb-0"><?php echo __('permission_matrix_help'); ?></p>
            <p class="text-muted small mb-0 mt-2"><i class="fas fa-circle-info me-1"></i><?php echo __('accounting_permissions_matrix_note'); ?></p>
            <?php Rateb\App\Core\View::partial('accounting-permissions-note'); ?>
        </div>
        <a href="<?php echo rateb_app_url('access-control/matrix'); ?>" class="btn btn-primary">
            <i class="fas fa-sliders"></i> <?php echo __('open_matrix'); ?>
        </a>
    </div>
</div>
