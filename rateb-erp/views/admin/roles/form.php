<?php
/** @var array<string, array<int, array<string, mixed>>> $permissionGroups */
/** @var array<int, int> $selectedPermissions */
$isEdit = is_array($item) && (int) ($item['id'] ?? 0) > 0;
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('name'); ?></label>
                    <input class="form-control" name="name" value="<?php echo Rateb\App\Core\View::escape($item['name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('slug'); ?></label>
                    <input class="form-control" name="slug" value="<?php echo Rateb\App\Core\View::escape($item['slug'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('description'); ?></label>
                    <input class="form-control" name="description" value="<?php echo Rateb\App\Core\View::escape($item['description'] ?? ''); ?>">
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
                    <span><?php echo Rateb\App\Core\View::escape(__( $module)); ?></span>
                    <button type="button" class="btn btn-link btn-sm p-0" data-matrix-module="<?php echo Rateb\App\Core\View::escape($module); ?>"><?php echo __('toggle_module'); ?></button>
                </div>
                <div class="rateb-card-body">
                    <?php if ($module === 'accounting') { ?>
                    <p class="text-muted small mb-3"><?php echo __('accounting_permissions_role_note'); ?></p>
                    <?php } elseif ($module === 'branches') { ?>
                    <p class="text-muted small mb-3"><?php echo __('branches_permissions_matrix_note'); ?></p>
                    <?php } ?>
                    <div class="row g-2">
                        <?php foreach ($perms as $perm) {
                            $slug = (string) ($perm['slug'] ?? '');
                            $highlight = $slug === 'accounting.approve' ? ' border border-warning rounded p-2 bg-warning bg-opacity-10' : '';
                            ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check<?php echo $highlight; ?>">
                                <input class="form-check-input rateb-matrix-check" type="checkbox" name="permission_ids[]" value="<?php echo (int) $perm['id']; ?>" id="perm_<?php echo (int) $perm['id']; ?>" data-module="<?php echo Rateb\App\Core\View::escape($module); ?>"
                                    <?php echo in_array((int) $perm['id'], $selectedPermissions, true) ? ' checked' : ''; ?>>
                                <label class="form-check-label rateb-ar-text" for="perm_<?php echo (int) $perm['id']; ?>">
                                    <strong><?php echo Rateb\App\Core\View::escape(rateb_permission_label($perm)); ?></strong>
                                    <small class="text-muted d-block"><?php echo Rateb\App\Core\View::escape($slug); ?></small>
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
