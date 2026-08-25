<?php
/** @var array<string, array<int, array<string, mixed>>> $permissionGroups */
/** @var array<int, int> $selectedPermissions */
$isEdit = is_array($item) && (int) ($item['id'] ?? 0) > 0;
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
$roleSlug = (string) ($item['slug'] ?? '');
$isSuperAdminRole = $roleSlug === 'super-admin';
$viewerRoles = is_array($viewerRoles ?? null) ? $viewerRoles : [];
$viewerPermissionCount = (int) ($viewerPermissionCount ?? 0);
$editingOwnAssignedRole = !empty($editingOwnAssignedRole);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <?php if ($isSuperAdminRole) { ?>
        <div class="alert alert-warning small mb-3" role="alert">
            <?php echo __('role_super_admin_matrix_bypass_note'); ?>
        </div>
        <?php } ?>
        <?php if ($viewerRoles !== [] || $viewerPermissionCount > 0) { ?>
        <div class="alert alert-info small mb-3" role="status">
            <div class="fw-semibold mb-1"><?php echo __('role_viewer_effective_rbac_title'); ?></div>
            <div><?php echo __('role_viewer_effective_rbac_perms', ['count' => $viewerPermissionCount]); ?></div>
            <?php if ($viewerRoles !== []) { ?>
            <ul class="mb-1 mt-2 ps-3">
                <?php foreach ($viewerRoles as $vr) {
                    $vrLabel = function_exists('rateb_role_label') ? rateb_role_label($vr) : (string) ($vr['name'] ?? '');
                    $vrCount = (int) ($vr['permission_count'] ?? 0);
                    $vrId = (int) ($vr['id'] ?? 0);
                    $editUrl = $vrId > 0 ? rateb_url($routePrefix . '/' . $vrId . '/edit') : '';
                    ?>
                <li>
                    <?php if ($editUrl !== '') { ?>
                    <a href="<?php echo Rateb\App\Core\View::escape($editUrl); ?>" data-rateb-full-nav="1">
                        <?php echo Rateb\App\Core\View::escape($vrLabel); ?>
                    </a>
                    <?php } else {
                        echo Rateb\App\Core\View::escape($vrLabel);
                    } ?>
                    — <?php echo (int) $vrCount; ?> <?php echo __('permissions'); ?>
                </li>
                <?php } ?>
            </ul>
            <?php } ?>
            <?php if ($isEdit && !$editingOwnAssignedRole) { ?>
            <div class="mt-1 text-warning"><?php echo __('role_viewer_editing_unassigned_role_note'); ?></div>
            <?php } ?>
        </div>
        <?php } ?>
        <form method="post" action="<?php echo $action; ?>" data-rateb-full-nav="1">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('name'); ?></label>
                    <input class="form-control" name="name" value="<?php echo Rateb\App\Core\View::escape(function_exists('rateb_role_label') && is_array($item ?? null) ? rateb_role_label($item) : ($item['name'] ?? '')); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('slug'); ?></label>
                    <input class="form-control" name="slug" value="<?php echo Rateb\App\Core\View::escape($item['slug'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('description'); ?></label>
                    <input class="form-control" name="description" value="<?php echo Rateb\App\Core\View::escape(function_exists('rateb_role_description') && is_array($item ?? null) ? rateb_role_description($item) : ($item['description'] ?? '')); ?>">
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <h3 class="h6 mb-0"><?php echo __('permission_matrix'); ?></h3>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-matrix-select-all="1"><?php echo __('select_all'); ?></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-matrix-select-none="1"><?php echo __('deselect_all'); ?></button>
                </div>
            </div>
            <?php foreach ($permissionGroups as $module => $perms) { ?>
            <div class="rateb-card mb-3">
                <div class="rateb-card-header py-2 d-flex justify-content-between align-items-center">
                    <span><?php echo Rateb\App\Core\View::escape(function_exists('rateb_module_label') ? rateb_module_label((string) $module) : __($module)); ?></span>
                    <button type="button" class="btn btn-link btn-sm p-0" data-matrix-module="<?php echo Rateb\App\Core\View::escape($module); ?>"><?php echo __('toggle_module'); ?></button>
                </div>
                <div class="rateb-card-body">
                    <?php if ($module === 'accounting') { ?>
                    <p class="text-muted small mb-3"><?php echo __('accounting_permissions_role_note'); ?></p>
                    <?php } elseif ($module === 'branches') { ?>
                    <p class="text-muted small mb-3"><?php echo __('branches_permissions_matrix_note'); ?></p>
                    <?php } elseif ($module === 'access' || $module === 'settings') { ?>
                    <p class="text-muted small mb-3"><?php echo __('access_settings_permissions_matrix_note'); ?></p>
                    <?php } ?>
                    <div class="row g-2">
                        <?php
                        $showPermSlug = !function_exists('rateb_locale') || rateb_locale() !== 'ar';
                        foreach ($perms as $perm) {
                            $slug = (string) ($perm['slug'] ?? '');
                            $highlight = $slug === 'accounting.approve' ? ' border border-warning rounded p-2 bg-warning bg-opacity-10' : '';
                            ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check<?php echo $highlight; ?>">
                                <input class="form-check-input rateb-matrix-check" type="checkbox" name="permission_ids[]" value="<?php echo (int) $perm['id']; ?>" id="perm_<?php echo (int) $perm['id']; ?>" data-module="<?php echo Rateb\App\Core\View::escape($module); ?>"
                                    <?php echo in_array((int) $perm['id'], $selectedPermissions, true) ? ' checked' : ''; ?>>
                                <label class="form-check-label rateb-ar-text" for="perm_<?php echo (int) $perm['id']; ?>">
                                    <strong><?php echo Rateb\App\Core\View::escape(rateb_permission_label($perm)); ?></strong>
                                    <?php if ($showPermSlug) { ?>
                                    <small class="text-muted d-block"><?php echo Rateb\App\Core\View::escape($slug); ?></small>
                                    <?php } ?>
                                </label>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php } ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
