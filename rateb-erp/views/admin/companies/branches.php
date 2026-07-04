<?php
/** @var array<string,mixed> $company */
/** @var array{items:array,total:int,page:int,per_page:int,pages:int} $branchList */
/** @var array<string,mixed> $listOpts */
/** @var int $companyId */
/** @var string $csrf */
/** @var string $newPortalUrl */
/** @var string $routePrefix */
/** @var string $branchAction */
/** @var string $listBaseUrl */
$branchCount = (int) ($company['branch_count'] ?? 0);
$limitEff = (int) ($company['branch_limit_effective'] ?? 0);
$limitSet = (int) ($company['branch_limit'] ?? 0);
$canAdd = !empty($company['can_add_branch']);
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

        <?php Rateb\App\Core\View::partial('admin/companies/branch-list-section', get_defined_vars()); ?>

        <?php if ($canAdd) { ?>
        <details class="border rounded p-3 mt-3" open>
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
        <div class="alert alert-warning py-2 small mb-0 mt-3"><?php echo __('branch_limit_reached_cp_hint'); ?></div>
        <?php } ?>
    </div>
</div>
