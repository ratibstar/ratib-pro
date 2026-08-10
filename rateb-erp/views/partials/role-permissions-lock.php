<?php
/**
 * Lock toggle + expandable permission editor for one role.
 *
 * @var array<string, mixed> $role
 * @var array<string, array<int, array<string, mixed>>> $permissionGroups
 * @var array<int, int> $selectedPermissions
 * @var string $csrf
 * @var string $saveAction  POST URL
 * @var string|null $returnUrl
 * @var bool $showAssignCheckbox  when true, include role_ids[] checkbox for user forms
 * @var array<int, int> $selectedRoles
 * @var bool $nestedSafe  true when rendered inside another <form> (no nested form tag)
 */
$roleId = (int) ($role['id'] ?? 0);
$slug = (string) ($role['slug'] ?? '');
$label = function_exists('rateb_role_label') ? rateb_role_label($role) : (string) ($role['name'] ?? $slug);
$panelId = 'role-perm-panel-' . $roleId;
$selectedPermissions = array_map('intval', $selectedPermissions ?? []);
$selectedRoles = array_map('intval', $selectedRoles ?? []);
$showAssign = !empty($showAssignCheckbox);
$nestedSafe = !empty($nestedSafe);
$permCount = count($selectedPermissions);
$saveAction = (string) ($saveAction ?? '');
$returnUrl = (string) ($returnUrl ?? '');
$csrf = (string) ($csrf ?? '');
$rbacScope = (string) ($rbacScope ?? 'platform');
?>
<div class="rateb-role-lock-card border rounded mb-2 p-2" data-role-lock="<?php echo $roleId; ?>">
    <div class="d-flex flex-wrap align-items-center gap-2 justify-content-between">
        <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
            <?php if ($showAssign) { ?>
            <div class="form-check m-0">
                <input class="form-check-input user-role-checkbox" type="checkbox" name="role_ids[]"
                       value="<?php echo $roleId; ?>" id="role_<?php echo $roleId; ?>"
                       data-role-slug="<?php echo Rateb\App\Core\View::escape($slug); ?>"
                       <?php echo in_array($roleId, $selectedRoles, true) ? ' checked' : ''; ?>>
                <label class="form-check-label" for="role_<?php echo $roleId; ?>">
                    <strong><?php echo Rateb\App\Core\View::escape($label); ?></strong>
                    <small class="text-muted">(<?php echo Rateb\App\Core\View::escape($slug); ?>)</small>
                </label>
            </div>
            <?php } else { ?>
            <div class="min-w-0">
                <strong class="d-block text-truncate"><?php echo Rateb\App\Core\View::escape($label); ?></strong>
                <small class="text-muted"><?php echo Rateb\App\Core\View::escape($slug); ?></small>
            </div>
            <?php } ?>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button"
                    class="badge text-bg-secondary rateb-role-lock-count border-0"
                    data-role-count="<?php echo $roleId; ?>"
                    data-role-lock-toggle="<?php echo $roleId; ?>"
                    data-bs-target="#<?php echo $panelId; ?>"
                    title="<?php echo Rateb\App\Core\View::escape(__('role_lock_open_permissions')); ?>">
                <?php echo (int) $permCount; ?> <?php echo __('permissions'); ?>
            </button>
            <button type="button"
                    class="btn btn-sm btn-outline-warning rateb-role-lock-toggle"
                    data-role-lock-toggle="<?php echo $roleId; ?>"
                    data-bs-target="#<?php echo $panelId; ?>"
                    aria-expanded="false"
                    aria-controls="<?php echo $panelId; ?>"
                    title="<?php echo Rateb\App\Core\View::escape(__('role_lock_open_permissions')); ?>">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span class="d-none d-sm-inline ms-1"><?php echo __('role_lock_configure'); ?></span>
            </button>
        </div>
    </div>
    <div class="collapse mt-2" id="<?php echo $panelId; ?>">
        <?php if ($nestedSafe) { ?>
        <div class="rateb-role-lock-form border-top pt-2"
             data-role-lock-form="<?php echo $roleId; ?>"
             data-save-action="<?php echo Rateb\App\Core\View::escape($saveAction); ?>"
             data-csrf="<?php echo Rateb\App\Core\View::escape($csrf); ?>"
             data-scope="<?php echo Rateb\App\Core\View::escape($rbacScope); ?>">
        <?php } else { ?>
        <form method="post" action="<?php echo Rateb\App\Core\View::escape($saveAction); ?>" class="rateb-role-lock-form border-top pt-2" data-role-lock-form="<?php echo $roleId; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php if ($returnUrl !== '') { ?>
            <input type="hidden" name="return" value="<?php echo Rateb\App\Core\View::escape($returnUrl); ?>">
            <?php } ?>
            <input type="hidden" name="scope" value="<?php echo Rateb\App\Core\View::escape($rbacScope); ?>">
        <?php } ?>
            <p class="small text-muted mb-2"><?php echo __('role_lock_help'); ?></p>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-matrix-select-all="1"><?php echo __('select_all'); ?></button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-matrix-select-none="1"><?php echo __('deselect_all'); ?></button>
                <?php if ($nestedSafe) { ?>
                <button type="button" class="btn btn-primary btn-sm" data-role-lock-save="<?php echo $roleId; ?>">
                    <i class="fas fa-save"></i> <?php echo __('save'); ?>
                </button>
                <?php } else { ?>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
                <?php } ?>
            </div>
            <div class="rateb-role-lock-perms" style="max-height: 22rem; overflow: auto;">
                <?php foreach (($permissionGroups ?? []) as $module => $perms) { ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="small"><?php echo Rateb\App\Core\View::escape(function_exists('rateb_module_label') ? rateb_module_label((string) $module) : (string) $module); ?></strong>
                        <button type="button" class="btn btn-link btn-sm p-0" data-matrix-module="<?php echo Rateb\App\Core\View::escape((string) $module); ?>"><?php echo __('toggle_module'); ?></button>
                    </div>
                    <div class="row g-1">
                        <?php foreach ($perms as $perm) {
                            $pid = (int) ($perm['id'] ?? 0);
                            ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check m-0">
                                <input class="form-check-input rateb-matrix-check" type="checkbox"
                                       <?php echo $nestedSafe ? '' : 'name="permission_ids[]"'; ?>
                                       value="<?php echo $pid; ?>"
                                       id="r<?php echo $roleId; ?>_p<?php echo $pid; ?>"
                                       data-module="<?php echo Rateb\App\Core\View::escape((string) $module); ?>"
                                       data-perm-id="<?php echo $pid; ?>"
                                       <?php echo in_array($pid, $selectedPermissions, true) ? ' checked' : ''; ?>>
                                <label class="form-check-label small" for="r<?php echo $roleId; ?>_p<?php echo $pid; ?>">
                                    <?php echo Rateb\App\Core\View::escape(function_exists('rateb_permission_label') ? rateb_permission_label($perm) : (string) ($perm['slug'] ?? '')); ?>
                                </label>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
            <div class="mt-2">
                <?php if ($nestedSafe) { ?>
                <button type="button" class="btn btn-primary btn-sm" data-role-lock-save="<?php echo $roleId; ?>">
                    <i class="fas fa-save"></i> <?php echo __('save'); ?>
                </button>
                <span class="small text-success ms-2 d-none" data-role-lock-ok="<?php echo $roleId; ?>"><?php echo __('role_lock_saved'); ?></span>
                <?php } else { ?>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
                <?php } ?>
            </div>
        <?php if ($nestedSafe) { ?>
        </div>
        <?php } else { ?>
        </form>
        <?php } ?>
    </div>
</div>
