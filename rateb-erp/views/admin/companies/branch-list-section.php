<?php
/** @var array{items:array,total:int,page:int,per_page:int,pages:int} $branchList */
/** @var array<string,mixed> $listOpts */
/** @var int $companyId */
/** @var string $csrf */
/** @var string $branchAction */
/** @var string $listBaseUrl */
$branches = $branchList['items'] ?? [];
$branchListTotal = (int) ($branchList['total'] ?? 0);
$branchListPage = (int) ($branchList['page'] ?? 1);
$branchListPerPage = (int) ($branchList['per_page'] ?? 25);
$branchListPages = (int) ($branchList['pages'] ?? 1);
$listOpts = $listOpts ?? [];
$listUrl = static function (array $extra = []) use ($listBaseUrl, $listOpts, $branchListPerPage): string {
    $q = array_merge([
        'q' => $listOpts['q'] ?? '',
        'status' => $listOpts['status'] ?? '',
        'branch_type' => $listOpts['branch_type'] ?? '',
        'archive' => $listOpts['archive'] ?? '',
        'sort' => $listOpts['sort'] ?? 'name',
        'dir' => $listOpts['dir'] ?? 'asc',
        'per_page' => $branchListPerPage,
        'page' => 1,
    ], $extra);
    $q = array_filter($q, static fn ($v): bool => $v !== null && $v !== '' && $v !== 0);
    return $listBaseUrl . (str_contains($listBaseUrl, '?') ? '&' : '?') . http_build_query($q);
};
?>
<form method="get" action="<?php echo Rateb\App\Core\View::escape($listBaseUrl); ?>" class="row g-2 mb-3 align-items-end">
    <div class="col-md-3">
        <label class="form-label small mb-0"><?php echo __('search'); ?></label>
        <input type="search" name="q" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($listOpts['q'] ?? '')); ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label small mb-0"><?php echo __('status'); ?></label>
        <select name="status" class="form-select form-select-sm">
            <option value=""><?php echo __('all'); ?></option>
            <option value="active"<?php echo ($listOpts['status'] ?? '') === 'active' ? ' selected' : ''; ?>><?php echo __('active'); ?></option>
            <option value="inactive"<?php echo ($listOpts['status'] ?? '') === 'inactive' ? ' selected' : ''; ?>><?php echo __('inactive'); ?></option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small mb-0"><?php echo __('branch_filter_type'); ?></label>
        <select name="branch_type" class="form-select form-select-sm">
            <option value=""><?php echo __('all'); ?></option>
            <option value="main"<?php echo ($listOpts['branch_type'] ?? '') === 'main' ? ' selected' : ''; ?>><?php echo __('main_branch'); ?></option>
            <option value="child"<?php echo ($listOpts['branch_type'] ?? '') === 'child' ? ' selected' : ''; ?>><?php echo __('branch_child'); ?></option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small mb-0"><?php echo __('branch_archive_filter'); ?></label>
        <select name="archive" class="form-select form-select-sm">
            <option value=""><?php echo __('branch_archive_active_only'); ?></option>
            <option value="archived"<?php echo ($listOpts['archive'] ?? '') === 'archived' ? ' selected' : ''; ?>><?php echo __('branch_archived'); ?></option>
            <option value="all"<?php echo ($listOpts['archive'] ?? '') === 'all' ? ' selected' : ''; ?>><?php echo __('all'); ?></option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small mb-0"><?php echo __('sort'); ?></label>
        <select name="sort" class="form-select form-select-sm">
            <option value="name"<?php echo ($listOpts['sort'] ?? '') === 'name' ? ' selected' : ''; ?>><?php echo __('branch_name'); ?></option>
            <option value="code"<?php echo ($listOpts['sort'] ?? '') === 'code' ? ' selected' : ''; ?>><?php echo __('branch_code'); ?></option>
            <option value="status"<?php echo ($listOpts['sort'] ?? '') === 'status' ? ' selected' : ''; ?>><?php echo __('status'); ?></option>
            <option value="created_at"<?php echo ($listOpts['sort'] ?? '') === 'created_at' ? ' selected' : ''; ?>><?php echo __('created_at'); ?></option>
        </select>
    </div>
    <div class="col-md-1">
        <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-search"></i></button>
    </div>
</form>

<form method="post" action="<?php echo Rateb\App\Core\View::escape($branchAction); ?>" id="branch-bulk-form-<?php echo (int) $companyId; ?>" class="mb-2 d-flex flex-wrap gap-2 align-items-center">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <input type="hidden" name="action" value="bulk_branch">
    <select name="bulk_action" class="form-select form-select-sm" style="width:auto">
        <option value="enable"><?php echo __('activate_branch'); ?></option>
        <option value="disable"><?php echo __('deactivate_branch'); ?></option>
        <option value="archive"><?php echo __('branch_archive'); ?></option>
        <option value="restore"><?php echo __('branch_restore'); ?></option>
    </select>
    <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo __('bulk_apply'); ?></button>
</form>

<?php if ($branches !== []) { ?>
<div class="table-responsive mb-2">
    <table class="table table-sm align-middle mb-0">
        <thead>
        <tr>
            <th style="width:2rem"><input type="checkbox" id="branch-check-all-<?php echo (int) $companyId; ?>" onclick="document.querySelectorAll('.branch-bulk-<?php echo (int) $companyId; ?>').forEach(function(c){c.checked=this.checked;}.bind(this))"></th>
            <th><?php echo __('branch_name'); ?></th>
            <th><?php echo __('branch_code'); ?></th>
            <th><?php echo __('status'); ?></th>
            <th><?php echo __('login'); ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($branches as $branch) {
            $bid = (int) ($branch['id'] ?? 0);
            $portalUrl = function_exists('rateb_branch_portal_url') ? rateb_branch_portal_url($bid, $branch) : '';
            $isMain = !empty($branch['is_main']);
            $isActive = (string) ($branch['status'] ?? '') === 'active';
            $isArchived = (int) ($branch['is_archived'] ?? 0) === 1;
            ?>
        <tr<?php echo $isArchived ? ' class="opacity-75"' : ''; ?>>
            <td><input type="checkbox" class="branch-bulk-<?php echo (int) $companyId; ?>" form="branch-bulk-form-<?php echo (int) $companyId; ?>" name="branch_ids[]" value="<?php echo $bid; ?>"></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($branch['name'] ?? '')); ?><?php echo $isMain ? ' <span class="badge bg-info">' . __('main_branch') . '</span>' : ''; ?><?php echo $isArchived ? ' <span class="badge bg-secondary">' . __('branch_archived') . '</span>' : ''; ?></td>
            <td><code><?php echo Rateb\App\Core\View::escape((string) ($branch['code'] ?? '')); ?></code></td>
            <td><?php echo $isActive ? '<span class="badge bg-success">' . __('active') . '</span>' : '<span class="badge bg-warning text-dark">' . __('inactive') . '</span>'; ?></td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control font-monospace" readonly value="<?php echo Rateb\App\Core\View::escape($portalUrl); ?>">
                    <a href="<?php echo Rateb\App\Core\View::escape($portalUrl); ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a>
                </div>
            </td>
            <td class="text-nowrap">
                <?php if (!$isArchived) { ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-branch-<?php echo $bid; ?>"><i class="fas fa-edit"></i></button>
                <?php if (!$isMain) { ?>
                <form method="post" action="<?php echo Rateb\App\Core\View::escape($branchAction); ?>" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <input type="hidden" name="action" value="toggle_branch">
                    <input type="hidden" name="branch_id" value="<?php echo $bid; ?>">
                    <input type="hidden" name="status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-<?php echo $isActive ? 'warning' : 'success'; ?>"><i class="fas fa-power-off"></i></button>
                </form>
                <form method="post" action="<?php echo Rateb\App\Core\View::escape($branchAction); ?>" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <input type="hidden" name="action" value="archive_branch">
                    <input type="hidden" name="branch_id" value="<?php echo $bid; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-archive"></i></button>
                </form>
                <?php } ?>
                <?php } else { ?>
                <form method="post" action="<?php echo Rateb\App\Core\View::escape($branchAction); ?>" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <input type="hidden" name="action" value="restore_branch">
                    <input type="hidden" name="branch_id" value="<?php echo $bid; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-undo"></i> <?php echo __('branch_restore'); ?></button>
                </form>
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
<?php
    Rateb\App\Core\View::partial('pagination', [
        'page' => $branchListPage,
        'total' => $branchListTotal,
        'limit' => $branchListPerPage,
        'baseUrl' => $listBaseUrl,
        'preserveQuery' => array_filter([
            'q' => $listOpts['q'] ?? '',
            'status' => $listOpts['status'] ?? '',
            'branch_type' => $listOpts['branch_type'] ?? '',
            'archive' => $listOpts['archive'] ?? '',
            'sort' => $listOpts['sort'] ?? '',
            'dir' => $listOpts['dir'] ?? '',
            'per_page' => $branchListPerPage,
        ], static fn ($v): bool => $v !== null && $v !== ''),
    ]);
?>
<?php foreach ($branches as $branch) {
    if ((int) ($branch['is_archived'] ?? 0) === 1) {
        continue;
    }
    $bid = (int) ($branch['id'] ?? 0);
    $isActive = (string) ($branch['status'] ?? '') === 'active';
    ?>
<div class="modal fade" id="edit-branch-<?php echo $bid; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="<?php echo Rateb\App\Core\View::escape($branchAction); ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('edit'); ?> <?php echo __('branch_name'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-2">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <input type="hidden" name="action" value="update_branch">
                    <input type="hidden" name="branch_id" value="<?php echo $bid; ?>">
                    <div class="col-md-6"><label class="form-label small"><?php echo __('branch_name'); ?> *</label><input type="text" name="branch_name" class="form-control form-control-sm" required value="<?php echo Rateb\App\Core\View::escape((string) ($branch['name'] ?? '')); ?>"></div>
                    <div class="col-md-3"><label class="form-label small"><?php echo __('branch_code'); ?></label><input type="text" name="branch_code" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['code'] ?? '')); ?>"></div>
                    <div class="col-md-3"><label class="form-label small"><?php echo __('status'); ?></label><select name="branch_status" class="form-select form-select-sm"><option value="active"<?php echo $isActive ? ' selected' : ''; ?>><?php echo __('active'); ?></option><option value="inactive"<?php echo !$isActive ? ' selected' : ''; ?>><?php echo __('inactive'); ?></option></select></div>
                    <div class="col-md-4"><label class="form-label small"><?php echo __('phone'); ?></label><input type="text" name="branch_phone" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['phone'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label small"><?php echo __('email'); ?></label><input type="email" name="branch_email" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['email'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label small"><?php echo __('map_url'); ?></label><input type="text" name="branch_map_url" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['map_url'] ?? '')); ?>"></div>
                    <div class="col-12"><label class="form-label small"><?php echo __('address'); ?></label><input type="text" name="branch_address" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['address'] ?? '')); ?>"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary btn-sm"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>
<?php } else { ?>
<p class="small text-muted mb-3"><?php echo __('no_records'); ?></p>
<?php } ?>
