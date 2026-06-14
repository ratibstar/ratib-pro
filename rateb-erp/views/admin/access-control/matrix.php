<?php
/** @var array<int, array<string, mixed>> $roles */
/** @var array<string, array<int, array<string, mixed>>> $permissionGroups */
/** @var array<int, array<int, int>> $matrix */
?>
<div class="mb-3">
    <a href="<?php echo rateb_url('admin/access-control'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-right"></i> <?php echo __('access_control'); ?>
    </a>
</div>
<form method="post" action="<?php echo rateb_url('admin/access-control/matrix'); ?>" id="rateb-perm-matrix">
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
        <div class="rateb-card-body p-0">
            <div class="table-responsive">
                <table class="table rateb-table rateb-matrix-table mb-0">
                    <thead>
                    <tr>
                        <th class="rateb-matrix-sticky"><?php echo __('permissions'); ?></th>
                        <?php foreach ($roles as $role) { ?>
                        <th class="text-center rateb-matrix-role-col">
                            <div class="fw-semibold"><?php echo Rateb\App\Core\View::escape($role['name']); ?></div>
                            <small class="text-muted d-block"><?php echo Rateb\App\Core\View::escape($role['slug']); ?></small>
                            <button type="button" class="btn btn-link btn-sm p-0 mt-1" data-matrix-col="<?php echo (int) $role['id']; ?>"><?php echo __('toggle_column'); ?></button>
                        </th>
                        <?php } ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($permissionGroups as $module => $perms) { ?>
                    <tr class="rateb-matrix-module-row">
                        <td colspan="<?php echo count($roles) + 1; ?>">
                            <strong><?php echo Rateb\App\Core\View::escape(__($module)); ?></strong>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-2" data-matrix-module="<?php echo Rateb\App\Core\View::escape($module); ?>"><?php echo __('toggle_module'); ?></button>
                            <?php if ($module === 'accounting') { ?>
                            <div class="text-muted small fw-normal mt-1"><?php echo __('accounting_permissions_matrix_note'); ?></div>
                            <ul class="text-muted small fw-normal mt-1 mb-0">
                                <li><?php echo __('accounting_perm_approve_hint'); ?></li>
                                <li><?php echo __('accounting_perm_post_supplier_hint'); ?></li>
                                <li><?php echo __('accounting_perm_bank_import_hint'); ?></li>
                            </ul>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php foreach ($perms as $perm) {
                        $permId = (int) $perm['id'];
                        ?>
                    <tr data-module="<?php echo Rateb\App\Core\View::escape($module); ?>"<?php echo ($perm['slug'] ?? '') === 'accounting.approve' ? ' class="table-warning"' : ''; ?>>
                        <td class="rateb-matrix-sticky rateb-ar-text">
                            <div class="fw-semibold"><?php echo Rateb\App\Core\View::escape(rateb_permission_label($perm)); ?></div>
                            <small class="text-muted"><?php echo Rateb\App\Core\View::escape($perm['slug']); ?></small>
                        </td>
                        <?php foreach ($roles as $role) {
                            $roleId = (int) $role['id'];
                            $checked = in_array($permId, $matrix[$roleId] ?? [], true);
                            ?>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-flex justify-content-center">
                                <input class="form-check-input rateb-matrix-check" type="checkbox"
                                    name="matrix[<?php echo $roleId; ?>][]"
                                    value="<?php echo $permId; ?>"
                                    data-role="<?php echo $roleId; ?>"
                                    data-module="<?php echo Rateb\App\Core\View::escape($module); ?>"
                                    id="m_<?php echo $roleId; ?>_<?php echo $permId; ?>"
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
        <a href="<?php echo rateb_url('admin/roles'); ?>" class="btn btn-outline-secondary"><?php echo __('roles'); ?></a>
        <a href="<?php echo rateb_url('admin/users'); ?>" class="btn btn-outline-secondary"><?php echo __('users'); ?></a>
    </div>
</form>
