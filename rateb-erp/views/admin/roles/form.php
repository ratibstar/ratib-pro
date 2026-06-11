<?php
/** @var array<string, array<int, array<string, mixed>>> $permissionGroups */
/** @var array<int, int> $selectedPermissions */
$isEdit = !empty($item);
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
            <h3 class="h6 mb-3"><?php echo __('permission_matrix'); ?></h3>
            <?php foreach ($permissionGroups as $module => $perms) { ?>
            <div class="rateb-card mb-3">
                <div class="rateb-card-header py-2"><?php echo Rateb\App\Core\View::escape(__( $module)); ?></div>
                <div class="rateb-card-body">
                    <div class="row g-2">
                        <?php foreach ($perms as $perm) { ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permission_ids[]" value="<?php echo (int) $perm['id']; ?>" id="perm_<?php echo (int) $perm['id']; ?>"
                                    <?php echo in_array((int) $perm['id'], $selectedPermissions, true) ? ' checked' : ''; ?>>
                                <label class="form-check-label" for="perm_<?php echo (int) $perm['id']; ?>">
                                    <?php echo Rateb\App\Core\View::escape(rateb_permission_label($perm)); ?>
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
