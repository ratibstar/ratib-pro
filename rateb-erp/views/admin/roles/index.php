<?php
if (!empty($rbacScope) && function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
    Rateb\App\Core\View::partial('rbac-scope-tabs', [
        'rbacScope' => $rbacScope,
        'rbacBaseUrl' => $rbacBaseUrl ?? rateb_url($routePrefix ?? 'admin/roles'),
        'rbacOpsCompanyId' => (int) ($rbacOpsCompanyId ?? 0),
    ]);
}
if (!empty($listHelp)) { ?>
<div class="alert alert-secondary py-2 small mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape((string) $listHelp); ?>
</div>
<?php }

$useLockUi = !empty($roleLockUi)
    && !empty($permissionGroups)
    && is_array($items ?? null);

if ($useLockUi) {
    $returnUrl = function_exists('rateb_url_query')
        ? rateb_url_query(rateb_url($routePrefix ?? 'admin/roles'), ['scope' => (string) ($rbacScope ?? 'platform')])
        : rateb_url($routePrefix ?? 'admin/roles');
    ?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? __('roles')); ?></span>
        <span class="small text-muted"><?php echo __('role_lock_list_hint'); ?></span>
    </div>
    <div class="rateb-card-body">
        <?php foreach ($items as $role) {
            $rid = (int) ($role['id'] ?? 0);
            $saveAction = rateb_url(($routePrefix ?? 'admin/roles') . '/' . $rid . '/permissions');
            Rateb\App\Core\View::partial('role-permissions-lock', [
                'role' => $role,
                'permissionGroups' => $permissionGroups,
                'selectedPermissions' => $rolePermissionMap[$rid] ?? [],
                'csrf' => $csrf ?? '',
                'saveAction' => $saveAction,
                'returnUrl' => $returnUrl,
                'rbacScope' => $rbacScope ?? 'platform',
                'showAssignCheckbox' => false,
            ]);
        } ?>
        <?php if (($items ?? []) === []) { ?>
        <p class="text-muted mb-0"><?php echo __('no_data'); ?></p>
        <?php } ?>
    </div>
</div>
<script src="<?php echo rateb_asset('js/role-permissions-lock.js'); ?>"></script>
<?php
} else {
    Rateb\App\Core\View::partial('crud-index', get_defined_vars());
}
?>
