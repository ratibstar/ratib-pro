<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $roles */
/** @var array<int, array<string, mixed>> $companies */
/** @var array<int, int> $selectedRoles */
/** @var string|null $loginBarcode */
/** @var string|null $badgeQrUrl */
/** @var array<int, int> $selectedBranches */
/** @var array<int, array<int, array{value:int,label:string}>> $branchesByCompany */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
if (!empty($platformUserForm)) {
    // Keep for=platform on POST action so ops company scope cannot rewrite the save.
    $action = function_exists('rateb_url_query')
        ? rateb_url_query($isEdit ? rateb_url('admin/users/' . (int) $item['id']) : rateb_url('admin/users'), ['for' => 'platform'])
        : ($action . (str_contains($action, '?') ? '&' : '?') . 'for=platform');
} elseif (!empty($platformStaffForm)) {
    $action = function_exists('rateb_url_query')
        ? rateb_url_query($isEdit ? rateb_url('admin/users/' . (int) $item['id']) : rateb_url('admin/users'), ['for' => 'staff'])
        : ($action . (str_contains($action, '?') ? '&' : '?') . 'for=staff');
}
$cancelUrl = rateb_url($routePrefix);
if (!empty($platformUserForm) && function_exists('rateb_url_query')) {
    $cancelUrl = rateb_url_query(rateb_url('admin/users'), ['scope' => 'platform']);
} elseif (!empty($platformStaffForm) && function_exists('rateb_url_query')) {
    $cancelUrl = rateb_url_query(rateb_url('admin/users'), ['scope' => 'staff']);
}
?>
<?php if ($isEdit && !empty($loginBarcode)) {
    Rateb\App\Core\View::partial('login-badge-card', [
        'loginBarcode' => $loginBarcode,
        'badgeScanQrUrl' => $badgeScanQrUrl ?? '',
        'badgeLoginUrl' => $badgeLoginUrl ?? '',
        'csrf' => $csrf,
        'regenerateAction' => $badgeRegenerateAction ?? rateb_url($routePrefix . '/' . (int) $item['id'] . '/regenerate-barcode'),
    ]);
} ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <?php if (!empty($formHelp) && empty($platformUserForm) && empty($platformStaffForm)) { ?>
        <div class="alert alert-secondary py-2 small mb-3" role="status">
            <?php echo Rateb\App\Core\View::escape((string) $formHelp); ?>
        </div>
        <?php } ?>
        <form method="post" action="<?php echo $action; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php if (!empty($platformUserForm)) { ?>
            <input type="hidden" name="for" value="platform">
            <?php } elseif (!empty($platformStaffForm)) { ?>
            <input type="hidden" name="for" value="staff">
            <?php } ?>
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
                    <?php if ($isEdit) { ?>
                    <div class="form-text"><?php echo __('password_leave_blank_keep'); ?></div>
                    <?php } ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('companies'); ?></label>
                    <?php if (!empty($platformUserForm)) { ?>
                    <input type="hidden" name="company_id" value="">
                    <input class="form-control" type="text" value="<?php echo Rateb\App\Core\View::escape(__('users_type_platform_sa') . ' — ' . __('users_no_company')); ?>" disabled>
                    <?php } elseif (!empty($platformStaffForm)) { ?>
                    <input type="hidden" name="company_id" value="">
                    <input class="form-control" type="text" value="<?php echo Rateb\App\Core\View::escape(__('users_type_platform_staff') . ' — ' . __('users_no_company')); ?>" disabled>
                    <?php } else { ?>
                    <select class="form-select" name="company_id"<?php echo !empty($hideSuperAdminFlag) ? ' disabled' : ''; ?>>
                        <?php if (empty($hideSuperAdminFlag)) { ?>
                        <option value=""><?php echo __('users_type_platform_sa'); ?> — <?php echo __('users_no_company'); ?></option>
                        <?php } ?>
                        <?php
                        $selectedCompanyId = (int) ($item['company_id'] ?? ($defaultCompanyId ?? 0));
                        foreach ($companies as $co) { ?>
                        <option value="<?php echo (int) $co['id']; ?>"<?php echo $selectedCompanyId === (int) $co['id'] ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($co['name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <?php if (!empty($hideSuperAdminFlag) && !empty($defaultCompanyId)) { ?>
                    <input type="hidden" name="company_id" value="<?php echo (int) $defaultCompanyId; ?>">
                    <?php } ?>
                    <?php } ?>
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
                    <?php if (!empty($platformUserForm)) { ?>
                    <input type="hidden" name="is_super_admin" value="1">
                    <div class="alert alert-success py-2 small mb-0">
                        <i class="fas fa-shield-halved me-1"></i>
                        <?php echo Rateb\App\Core\View::escape(__('users_create_platform_sa_hint')); ?>
                    </div>
                    <?php } elseif (!empty($platformStaffForm)) { ?>
                    <input type="hidden" name="is_super_admin" value="0">
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="fas fa-user-shield me-1"></i>
                        <?php echo Rateb\App\Core\View::escape(__('users_create_platform_staff_hint')); ?>
                    </div>
                    <?php } elseif (empty($hideSuperAdminFlag)) { ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_super_admin" value="1" id="is_super_admin"<?php echo !empty($isSuperAdmin) ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="is_super_admin"><?php echo __('super_admin'); ?></label>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <div class="mt-4" id="user-branches-section" style="display:none">
                <h3 class="h6 mb-2"><?php echo __('assign_branches'); ?></h3>
                <p class="small text-muted mb-2" id="user-branches-hint"><?php echo __('branch_access_all_hint'); ?></p>
                <div class="row g-2" id="user-branches-list"></div>
            </div>
            <div class="mt-4">
                <h3 class="h6 mb-2"><?php echo __('assign_roles'); ?></h3>
                <?php if (!empty($platformUserForm)) { ?>
                <p class="small text-muted mb-0"><?php echo __('users_create_platform_sa_hint'); ?></p>
                <?php } elseif (!empty($platformStaffForm)) { ?>
                <p class="small text-muted mb-3"><?php echo __('users_staff_roles_lock_intro'); ?></p>
                <?php
                $returnStaff = function_exists('rateb_url_query')
                    ? rateb_url_query(
                        $isEdit ? rateb_url('admin/users/' . (int) $item['id'] . '/edit') : rateb_url('admin/users/create'),
                        ['for' => 'staff']
                    )
                    : rateb_url($routePrefix);
                foreach (($roles ?? []) as $role) {
                    $rid = (int) ($role['id'] ?? 0);
                    Rateb\App\Core\View::partial('role-permissions-lock', [
                        'role' => $role,
                        'permissionGroups' => $permissionGroups ?? [],
                        'selectedPermissions' => $rolePermissionMap[$rid] ?? [],
                        'csrf' => $csrf ?? '',
                        'saveAction' => rateb_url('admin/roles/' . $rid . '/permissions'),
                        'returnUrl' => $returnStaff,
                        'rbacScope' => 'platform',
                        'showAssignCheckbox' => true,
                        'selectedRoles' => $selectedRoles ?? [],
                        'nestedSafe' => true,
                    ]);
                }
                if (($roles ?? []) === []) { ?>
                <p class="text-muted"><?php echo __('no_data'); ?></p>
                <?php } ?>
                <script src="<?php echo rateb_asset('js/role-permissions-lock.js'); ?>"></script>
                <?php } else { ?>
                <p class="small text-muted mb-3"><?php echo __('branch_roles_form_intro'); ?></p>
                <?php
                $rolesGrouped = $rolesGrouped ?? [];
                $roleGroupMeta = [
                    'branch' => ['label' => __('role_group_branch'), 'icon' => 'fa-code-branch'],
                    'hq' => ['label' => __('role_group_hq'), 'icon' => 'fa-building'],
                    'operations' => ['label' => __('role_group_operations'), 'icon' => 'fa-boxes-stacked'],
                    'admin' => ['label' => __('role_group_admin'), 'icon' => 'fa-shield-halved'],
                    'other' => ['label' => __('role_group_other'), 'icon' => 'fa-user-tag'],
                ];
                foreach ($roleGroupMeta as $groupKey => $meta) {
                    $groupRoles = $rolesGrouped[$groupKey] ?? [];
                    if ($groupRoles === []) {
                        continue;
                    }
                    ?>
                <div class="mb-3">
                    <h4 class="h6 text-muted mb-2"><i class="fas <?php echo Rateb\App\Core\View::escape($meta['icon']); ?> me-1"></i><?php echo Rateb\App\Core\View::escape($meta['label']); ?></h4>
                    <div class="row g-2">
                        <?php foreach ($groupRoles as $role) { ?>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input user-role-checkbox" type="checkbox" name="role_ids[]" value="<?php echo (int) $role['id']; ?>" id="role_<?php echo (int) $role['id']; ?>"
                                    data-role-slug="<?php echo Rateb\App\Core\View::escape((string) ($role['slug'] ?? '')); ?>"
                                    <?php echo in_array((int) $role['id'], $selectedRoles, true) ? ' checked' : ''; ?>>
                                <label class="form-check-label" for="role_<?php echo (int) $role['id']; ?>">
                                    <?php echo Rateb\App\Core\View::escape(function_exists('rateb_role_label') ? rateb_role_label($role) : (string) ($role['name'] ?? '')); ?>
                                    <small class="text-muted">(<?php echo Rateb\App\Core\View::escape($role['slug']); ?>)</small>
                                </label>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
                <?php } ?>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo Rateb\App\Core\View::escape($cancelUrl); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var byCompany = <?php echo json_encode($branchesByCompany ?? [], JSON_UNESCAPED_UNICODE); ?>;
    var selected = <?php echo json_encode(array_values($selectedBranches ?? []), JSON_UNESCAPED_UNICODE); ?>;
    var branchRestrictedRoleIds = <?php echo json_encode(array_values($branchRestrictedRoleIds ?? []), JSON_UNESCAPED_UNICODE); ?>;
    var hintDefault = <?php echo json_encode(__('branch_access_all_hint'), JSON_UNESCAPED_UNICODE); ?>;
    var hintRestricted = <?php echo json_encode(__('branch_assignment_form_hint'), JSON_UNESCAPED_UNICODE); ?>;
    var hintBranchManager = <?php echo json_encode(__('branch_manager_single_branch_hint'), JSON_UNESCAPED_UNICODE); ?>;
    var companySelect = document.querySelector('select[name="company_id"]');
    var section = document.getElementById('user-branches-section');
    var list = document.getElementById('user-branches-list');
    var hint = document.getElementById('user-branches-hint');
    if (!companySelect || !section || !list) {
        return;
    }
    function hasBranchRestrictedRoleSelected() {
        if (!branchRestrictedRoleIds.length) {
            return false;
        }
        var checked = document.querySelectorAll('input[name="role_ids[]"]:checked');
        for (var i = 0; i < checked.length; i++) {
            var roleId = parseInt(checked[i].value, 10) || 0;
            if (branchRestrictedRoleIds.indexOf(roleId) !== -1) {
                return true;
            }
        }
        return false;
    }
    function isBranchManagerSelected() {
        var checked = document.querySelectorAll('input.user-role-checkbox:checked');
        for (var i = 0; i < checked.length; i++) {
            if ((checked[i].getAttribute('data-role-slug') || '') === 'branch_manager') {
                return true;
            }
        }
        return false;
    }
    function applyBranchRoleExclusivity(changed) {
        if (!changed || changed.type !== 'checkbox') {
            return;
        }
        var slug = changed.getAttribute('data-role-slug') || '';
        if (slug !== 'branch_manager' && slug !== 'branch_user') {
            return;
        }
        if (!changed.checked) {
            return;
        }
        document.querySelectorAll('input.user-role-checkbox').forEach(function (el) {
            var otherSlug = el.getAttribute('data-role-slug') || '';
            if (otherSlug === 'branch_manager' || otherSlug === 'branch_user') {
                if (el !== changed) {
                    el.checked = false;
                }
            }
        });
    }
    function updateBranchHint() {
        if (!hint) {
            return;
        }
        if (isBranchManagerSelected()) {
            hint.textContent = hintBranchManager;
        } else if (hasBranchRestrictedRoleSelected()) {
            hint.textContent = hintRestricted;
        } else {
            hint.textContent = hintDefault;
        }
    }
    function renderBranches() {
        var cid = parseInt(companySelect.value, 10) || 0;
        list.innerHTML = '';
        if (cid < 1 || !byCompany[cid] || !byCompany[cid].length) {
            section.style.display = 'none';
            return;
        }
        section.style.display = '';
        byCompany[cid].forEach(function (b) {
            var col = document.createElement('div');
            col.className = 'col-md-4';
            var checked = selected.indexOf(b.value) !== -1 ? ' checked' : '';
            col.innerHTML = '<div class="form-check">'
                + '<input class="form-check-input" type="checkbox" name="branch_ids[]" value="' + b.value + '" id="branch_' + b.value + '"' + checked + '>'
                + '<label class="form-check-label" for="branch_' + b.value + '">' + b.label + '</label>'
                + '</div>';
            list.appendChild(col);
        });
        updateBranchHint();
    }
    companySelect.addEventListener('change', function () {
        selected = [];
        renderBranches();
    });
    document.querySelectorAll('input.user-role-checkbox').forEach(function (el) {
        el.addEventListener('change', function () {
            applyBranchRoleExclusivity(el);
            updateBranchHint();
        });
    });
    renderBranches();
    updateBranchHint();
})();
</script>
