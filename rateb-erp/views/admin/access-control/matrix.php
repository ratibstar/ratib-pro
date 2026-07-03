<?php
/** @var array<int, array<string, mixed>> $roles */
/** @var array<string, array<int, array<string, mixed>>> $permissionGroups */
/** @var array<int, array<int, int>> $matrix */
?>
<div class="mb-3">
    <a href="<?php echo rateb_app_url('access-control'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-right"></i> <?php echo __('access_control'); ?>
    </a>
</div>
<form method="post" action="<?php echo rateb_app_url('access-control/matrix'); ?>" id="rateb-perm-matrix">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <div class="rateb-card mb-3">
        <div class="rateb-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><?php echo __('permission_matrix'); ?></span>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-matrix-select-all="1"><?php echo __('select_all'); ?></button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-matrix-select-none="1"><?php echo __('deselect_all'); ?></button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
            </div>
        </div>
        <div class="rateb-card-body py-2 px-3 border-bottom">
            <p class="text-muted small mb-1"><?php echo __('permission_matrix_help'); ?></p>
            <p class="text-muted small mb-0"><i class="fas fa-circle-info me-1"></i><?php echo __('permission_matrix_implies_note'); ?></p>
        </div>
        <div class="rateb-card-body p-0">
            <div class="rateb-matrix-wrap table-responsive">
                <table class="table rateb-table rateb-matrix-table mb-0">
                    <thead>
                    <tr>
                        <th class="rateb-matrix-sticky rateb-matrix-corner"><?php echo __('permissions'); ?></th>
                        <?php foreach ($roles as $role) {
                            $roleLabel = rateb_role_label($role);
                            ?>
                        <th class="text-center rateb-matrix-role-col">
                            <div class="rateb-matrix-role-head fw-semibold" title="<?php echo Rateb\App\Core\View::escape($roleLabel); ?>">
                                <?php echo Rateb\App\Core\View::escape($roleLabel); ?>
                            </div>
                            <code class="rateb-matrix-role-slug"><?php echo Rateb\App\Core\View::escape($role['slug']); ?></code>
                            <button type="button" class="btn btn-link btn-sm p-0 mt-1 rateb-matrix-toggle-btn" data-matrix-col="<?php echo (int) $role['id']; ?>"><?php echo __('toggle_column'); ?></button>
                        </th>
                        <?php } ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($permissionGroups as $module => $perms) { ?>
                    <tr class="rateb-matrix-module-row">
                        <td colspan="<?php echo count($roles) + 1; ?>">
                            <div class="rateb-matrix-module-inner">
                                <strong class="rateb-matrix-module-title"><?php echo Rateb\App\Core\View::escape(rateb_module_label($module)); ?></strong>
                                <button type="button" class="btn btn-link btn-sm p-0 rateb-matrix-toggle-btn" data-matrix-module="<?php echo Rateb\App\Core\View::escape($module); ?>"><?php echo __('toggle_module'); ?></button>
                            </div>
                            <?php if ($module === 'accounting') { ?>
                            <div class="rateb-matrix-module-note text-muted small fw-normal mt-2">
                                <?php echo __('accounting_permissions_matrix_note'); ?>
                                <ul class="mb-0 mt-1">
                                    <li><?php echo __('accounting_perm_approve_hint'); ?></li>
                                    <li><?php echo __('accounting_perm_post_supplier_hint'); ?></li>
                                    <li><?php echo __('accounting_perm_bank_import_hint'); ?></li>
                                </ul>
                            </div>
                            <?php } elseif ($module === 'branches') { ?>
                            <div class="rateb-matrix-module-note text-muted small fw-normal mt-2">
                                <?php echo __('branches_permissions_matrix_note'); ?>
                            </div>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php foreach ($perms as $perm) {
                        $permId = (int) $perm['id'];
                        $permLabel = rateb_permission_label($perm);
                        ?>
                    <tr data-module="<?php echo Rateb\App\Core\View::escape($module); ?>"<?php echo ($perm['slug'] ?? '') === 'accounting.approve' ? ' class="table-warning"' : ''; ?>>
                        <td class="rateb-matrix-sticky">
                            <div class="rateb-matrix-perm-label fw-semibold"><?php echo Rateb\App\Core\View::escape($permLabel); ?></div>
                            <?php
                            $permDesc = rateb_permission_description($perm);
                            if ($permDesc !== '') { ?>
                            <div class="rateb-matrix-perm-desc text-muted small"><?php echo Rateb\App\Core\View::escape($permDesc); ?></div>
                            <?php } ?>
                            <code class="rateb-matrix-perm-slug"><?php echo Rateb\App\Core\View::escape($perm['slug']); ?></code>
                        </td>
                        <?php foreach ($roles as $role) {
                            $roleId = (int) $role['id'];
                            $checked = in_array($permId, $matrix[$roleId] ?? [], true);
                            ?>
                        <td class="text-center rateb-matrix-toggle-cell">
                            <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                                <input class="form-check-input rateb-matrix-check" type="checkbox"
                                    name="matrix[<?php echo $roleId; ?>][]"
                                    value="<?php echo $permId; ?>"
                                    data-role="<?php echo $roleId; ?>"
                                    data-module="<?php echo Rateb\App\Core\View::escape($module); ?>"
                                    id="m_<?php echo $roleId; ?>_<?php echo $permId; ?>"
                                    title="<?php echo Rateb\App\Core\View::escape($permLabel); ?>"
                                    <?php echo $checked ? ' checked' : ''; ?>>
                            </div>
                        </td>
                        <?php } ?>
                    </tr>
                    <?php } } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('roles'); ?>" class="btn btn-outline-secondary"><?php echo __('roles'); ?></a>
        <a href="<?php echo rateb_app_url('users'); ?>" class="btn btn-outline-secondary"><?php echo __('users'); ?></a>
    </div>
</form>
