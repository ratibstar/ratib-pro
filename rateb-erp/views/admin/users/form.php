<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $roles */
/** @var array<int, array<string, mixed>> $companies */
/** @var array<int, int> $selectedRoles */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('name'); ?></label>
                    <input class="form-control" name="name" value="<?php echo Rateb\App\Core\View::escape($item['name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('email'); ?></label>
                    <input class="form-control" type="email" name="email" value="<?php echo Rateb\App\Core\View::escape($item['email'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('phone'); ?></label>
                    <input class="form-control" name="phone" value="<?php echo Rateb\App\Core\View::escape($item['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('password'); ?></label>
                    <input class="form-control" type="password" name="password" autocomplete="new-password"<?php echo $isEdit ? '' : ' required'; ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('companies'); ?></label>
                    <select class="form-select" name="company_id">
                        <option value=""><?php echo __('super_admin'); ?> / <?php echo __('platform'); ?></option>
                        <?php foreach ($companies as $co) { ?>
                        <option value="<?php echo (int) $co['id']; ?>"<?php echo (int) ($item['company_id'] ?? 0) === (int) $co['id'] ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($co['name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-select" name="status">
                        <?php foreach (['active', 'inactive', 'suspended'] as $st) { ?>
                        <option value="<?php echo $st; ?>"<?php echo ($item['status'] ?? 'active') === $st ? ' selected' : ''; ?>><?php echo __( $st); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('language'); ?></label>
                    <select class="form-select" name="locale">
                        <option value="ar"<?php echo ($item['locale'] ?? 'ar') === 'ar' ? ' selected' : ''; ?>>عربي</option>
                        <option value="en"<?php echo ($item['locale'] ?? '') === 'en' ? ' selected' : ''; ?>>EN</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_super_admin" value="1" id="is_super_admin"<?php echo !empty($isSuperAdmin) ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="is_super_admin"><?php echo __('super_admin'); ?></label>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <h3 class="h6 mb-2"><?php echo __('assign_roles'); ?></h3>
                <div class="row g-2">
                    <?php foreach ($roles as $role) { ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="role_ids[]" value="<?php echo (int) $role['id']; ?>" id="role_<?php echo (int) $role['id']; ?>"
                                <?php echo in_array((int) $role['id'], $selectedRoles, true) ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="role_<?php echo (int) $role['id']; ?>">
                                <?php echo Rateb\App\Core\View::escape($role['name']); ?>
                                <small class="text-muted">(<?php echo Rateb\App\Core\View::escape($role['slug']); ?>)</small>
                            </label>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
