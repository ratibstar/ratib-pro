<?php $s = $stats ?? []; ?>
<?php $tenantScoped = !empty($scopedCompanyId); ?>
<?php $catalogLocked = function_exists('rateb_tenant_permission_catalog_locked') && rateb_tenant_permission_catalog_locked(); ?>
<?php $isSa = function_exists('rateb_is_super_admin') && rateb_is_super_admin(); ?>
<?php
if ($isSa) {
    Rateb\App\Core\View::partial('rbac-scope-tabs', [
        'rbacScope' => $rbacScope ?? 'platform',
        'rbacBaseUrl' => rateb_app_url('access-control'),
        'rbacOpsCompanyId' => (int) ($rbacOpsCompanyId ?? 0),
    ]);
}
$matrixHref = rateb_app_url('access-control/matrix');
$rolesHref = rateb_app_url('roles');
$usersHref = rateb_app_url('users');
if ($isSa && function_exists('rateb_url_query')) {
    $scope = (string) ($rbacScope ?? 'platform');
    $matrixHref = rateb_url_query($matrixHref, ['scope' => $scope]);
    $rolesHref = rateb_url_query($rolesHref, ['scope' => $scope]);
    $usersHref = rateb_url_query(
        rateb_url('admin/users'),
        ['scope' => $scope === 'platform' ? 'staff' : 'company']
    );
}
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="rateb-widget">
            <div class="rateb-widget-value"><?php echo (int) ($s['users'] ?? 0); ?></div>
            <div class="rateb-widget-label"><?php echo $tenantScoped ? __('users_scope_company') : __('rbac_scope_platform'); ?></div>
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
<?php if ($isSa && !$tenantScoped) { ?>
<div class="alert alert-info py-2 small mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape(__('rbac_platform_staff_workflow')); ?>
</div>
<?php } elseif ($tenantScoped) { ?>
<p class="text-muted small mb-3"><i class="fas fa-building me-1"></i><?php echo __('access_control_tenant_scope_note'); ?></p>
<?php } ?>
<div class="row g-3">
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h3 class="h6"><i class="fas fa-users me-2"></i><?php echo !$tenantScoped && $isSa ? __('users_scope_staff') : __('users'); ?></h3>
                <p class="text-muted small"><?php echo !$tenantScoped && $isSa ? __('access_platform_users_help') : __('access_users_help'); ?></p>
                <a href="<?php echo Rateb\App\Core\View::escape($usersHref); ?>" class="btn btn-primary btn-sm"><?php echo __('manage'); ?></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h3 class="h6"><i class="fas fa-user-shield me-2"></i><?php echo __('roles'); ?></h3>
                <p class="text-muted small"><?php echo !$tenantScoped && $isSa ? __('access_platform_roles_help') : __('access_roles_help'); ?></p>
                <a href="<?php echo Rateb\App\Core\View::escape($rolesHref); ?>" class="btn btn-primary btn-sm"><?php echo __('manage'); ?></a>
            </div>
        </div>
    </div>
    <?php if (!$catalogLocked) { ?>
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h3 class="h6"><i class="fas fa-key me-2"></i><?php echo __('permissions'); ?></h3>
                <p class="text-muted small"><?php echo __('access_permissions_help'); ?></p>
                <a href="<?php echo rateb_app_url('permissions'); ?>" class="btn btn-primary btn-sm"><?php echo __('manage'); ?></a>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<div class="rateb-card mt-3">
    <div class="rateb-card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="h6 mb-1"><i class="fas fa-table-cells me-2"></i><?php echo __('permission_matrix'); ?></h3>
            <p class="text-muted small mb-0"><?php echo !$tenantScoped && $isSa ? __('permission_matrix_platform_scope_note') : __('permission_matrix_help'); ?></p>
            <p class="text-muted small mb-0 mt-2"><i class="fas fa-circle-info me-1"></i><?php echo __('accounting_permissions_matrix_note'); ?></p>
            <?php Rateb\App\Core\View::partial('accounting-permissions-note'); ?>
        </div>
        <a href="<?php echo Rateb\App\Core\View::escape($matrixHref); ?>" class="btn btn-primary">
            <i class="fas fa-sliders"></i> <?php echo __('open_matrix'); ?>
        </a>
    </div>
</div>
