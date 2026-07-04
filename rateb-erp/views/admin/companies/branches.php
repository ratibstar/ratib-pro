<?php
/** @var array<string, mixed> $company */
/** @var array<int, array<string, mixed>> $branches */
/** @var int $companyId */
/** @var string $csrf */
/** @var string $newPortalUrl */
/** @var string $routePrefix */
$branchCount = (int) ($company['branch_count'] ?? 0);
$limitEff = (int) ($company['branch_limit_effective'] ?? 0);
$limitSet = (int) ($company['branch_limit'] ?? 0);
$canAdd = !empty($company['can_add_branch']);
$branchAction = rateb_url($routePrefix . '/' . $companyId . '/branches');
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-right"></i> <?php echo __('companies'); ?></a>
            <a href="<?php echo rateb_url($routePrefix . '/branches'); ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-list"></i> <?php echo __('manage_branches_cp'); ?></a>
        </div>
    </div>
    <div class="rateb-card-body">
        <?php if ($newPortalUrl !== '') { ?>
        <div class="alert alert-info">
            <strong><i class="fas fa-link me-1"></i><?php echo __('login'); ?>:</strong>
            <div class="input-group input-group-sm mt-2">
                <input type="text" class="form-control font-monospace user-select-all" readonly value="<?php echo Rateb\App\Core\View::escape($newPortalUrl); ?>" id="new-branch-portal-url">
                <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('new-branch-portal-url').value)"><i class="fas fa-copy"></i></button>
                <a href="<?php echo Rateb\App\Core\View::escape($newPortalUrl); ?>" class="btn btn-primary" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a>
            </div>
        </div>
        <?php } ?>

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1">
                    <i class="fas fa-building text-primary"></i>
                    <?php echo Rateb\App\Core\View::escape((string) ($company['name'] ?? '')); ?>
                    <span class="text-muted">#<?php echo (int) $companyId; ?></span>
                </h2>
                <div class="small text-muted">
                    <code><?php echo Rateb\App\Core\View::escape((string) ($company['slug'] ?? '')); ?></code>
                    · <?php echo Rateb\App\Core\View::escape((string) ($company['status'] ?? '')); ?>
                    · <?php echo __('branches'); ?>: <strong><?php echo $branchCount; ?></strong> / <?php echo $limitEff; ?>
                </div>
            </div>
            <form method="post" action="<?php echo Rateb\App\Core\View::escape($branchAction); ?>" class="d-flex align-items-end gap-2 flex-wrap">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <input type="hidden" name="action" value="set_branch_limit">
                <div>
                    <label class="form-label small mb-0"><?php echo __('branch_limit'); ?></label>
                    <input type="number" name="branch_limit" class="form-control form-control-sm" style="width:6rem" min="0" max="999" value="<?php echo $limitSet > 0 ? $limitSet : $limitEff; ?>">
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-check"></i> <?php echo __('save'); ?></button>
            </form>
        </div>

        <?php if ($branches !== []) { ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
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
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($branch['name'] ?? '')); ?><?php echo $isMain ? ' <span class="badge bg-info">' . __('main_branch') . '</span>' : ''; ?></td>
                    <td><code><?php echo Rateb\App\Core\View::escape((string) ($branch['code'] ?? '')); ?></code></td>
                    <td><?php echo $isActive ? '<span class="badge bg-success">' . __('active') . '</span>' : '<span class="badge bg-warning text-dark">' . __('inactive') . '</span>'; ?></td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" readonly value="<?php echo Rateb\App\Core\View::escape($portalUrl); ?>" id="branch-url-<?php echo $bid; ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('branch-url-<?php echo $bid; ?>').value)"><i class="fas fa-copy"></i></button>
                            <a href="<?php echo Rateb\App\Core\View::escape($portalUrl); ?>" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-branch-<?php echo $bid; ?>">
                            <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
                        </button>
                        <?php if (!$isMain) { ?>
                        <form method="post" action="<?php echo Rateb\App\Core\View::escape($branchAction); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <input type="hidden" name="action" value="toggle_branch">
                            <input type="hidden" name="branch_id" value="<?php echo $bid; ?>">
                            <input type="hidden" name="status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-<?php echo $isActive ? 'warning' : 'success'; ?>"><?php echo $isActive ? __('deactivate_branch') : __('activate_branch'); ?></button>
                        </form>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php foreach ($branches as $branch) {
            $bid = (int) ($branch['id'] ?? 0);
            $isActive = (string) ($branch['status'] ?? '') === 'active';
            ?>
        <div class="modal fade" id="edit-branch-<?php echo $bid; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="post" action="<?php echo Rateb\App\Core\View::escape($branchAction); ?>">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo __('edit'); ?> <?php echo __('branch_name'); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo __('close'); ?>"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <input type="hidden" name="action" value="update_branch">
                            <input type="hidden" name="branch_id" value="<?php echo $bid; ?>">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small"><?php echo __('branch_name'); ?> *</label>
                                    <input type="text" name="branch_name" class="form-control form-control-sm" required value="<?php echo Rateb\App\Core\View::escape((string) ($branch['name'] ?? '')); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small"><?php echo __('branch_code'); ?></label>
                                    <input type="text" name="branch_code" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['code'] ?? '')); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small"><?php echo __('status'); ?></label>
                                    <select name="branch_status" class="form-select form-select-sm">
                                        <option value="active"<?php echo $isActive ? ' selected' : ''; ?>><?php echo __('active'); ?></option>
                                        <option value="inactive"<?php echo !$isActive ? ' selected' : ''; ?>><?php echo __('inactive'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small"><?php echo __('phone'); ?></label>
                                    <input type="text" name="branch_phone" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['phone'] ?? '')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small"><?php echo __('email'); ?></label>
                                    <input type="email" name="branch_email" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['email'] ?? '')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small"><?php echo __('map_url'); ?></label>
                                    <input type="text" name="branch_map_url" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['map_url'] ?? '')); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small"><?php echo __('address'); ?></label>
                                    <input type="text" name="branch_address" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape((string) ($branch['address'] ?? '')); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php } ?>
        <?php } else { ?>
        <p class="small text-muted mb-3"><?php echo __('no_records'); ?></p>
        <?php } ?>

        <?php if ($canAdd) { ?>
        <details class="border rounded p-3" open>
            <summary class="fw-semibold"><i class="fas fa-plus-circle text-primary"></i> <?php echo __('create'); ?> <?php echo __('branch_name'); ?></summary>
            <form method="post" action="<?php echo Rateb\App\Core\View::escape($branchAction); ?>" class="row g-2 mt-3">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <input type="hidden" name="action" value="create_branch">
                <div class="col-md-4">
                    <label class="form-label small"><?php echo __('branch_name'); ?> *</label>
                    <input type="text" name="branch_name" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><?php echo __('branch_code'); ?></label>
                    <input type="text" name="branch_code" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?php echo __('phone'); ?></label>
                    <input type="text" name="branch_phone" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?php echo __('email'); ?></label>
                    <input type="email" name="branch_email" class="form-control form-control-sm">
                </div>
                <div class="col-12">
                    <label class="form-label small"><?php echo __('address'); ?></label>
                    <input type="text" name="branch_address" class="form-control form-control-sm">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-store"></i> <?php echo __('create'); ?></button>
                </div>
            </form>
        </details>
        <?php } else { ?>
        <div class="alert alert-warning py-2 small mb-0"><?php echo __('branch_limit_reached_cp_hint'); ?></div>
        <?php } ?>
    </div>
</div>
