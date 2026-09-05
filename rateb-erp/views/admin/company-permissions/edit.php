<?php
/** @var array<string, mixed> $company */
/** @var array<string, string> $moduleCatalog */
/** @var list<string> $selectedModules */
/** @var array<string, mixed>|null $limits */
/** @var string $routePrefix */
$cid = (int) ($company['id'] ?? 0);
$companyName = (string) ($company['name'] ?? '');
$selectedModules = is_array($selectedModules ?? null) ? $selectedModules : [];
$moduleCatalog = is_array($moduleCatalog ?? null) ? $moduleCatalog : [];
$lockedCore = ['dashboard', 'notifications'];
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/company-permissions.css'); ?>">
<div class="rateb-card rateb-cp-edit">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <span><?php echo Rateb\App\Core\View::escape($title ?? __('company_permissions')); ?></span>
            <p class="form-text mb-0 mt-1"><?php echo __('company_permissions_edit_help'); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-right"></i> <?php echo __('company_permissions'); ?>
            </a>
            <a href="<?php echo rateb_url('admin/companies/' . $cid . '/edit'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-building"></i> <?php echo __('edit'); ?> <?php echo __('companies'); ?>
            </a>
        </div>
    </div>
    <div class="rateb-card-body">
        <?php
        $companyStatus = (string) ($company['status'] ?? 'pending');
        $canActivateCompany = ($companyStatus === 'suspended')
            && (rateb_is_super_admin() || rateb_can('companies.manage'));
        ?>
        <div class="alert alert-<?php echo $companyStatus === 'suspended' ? 'warning' : 'info'; ?> d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong><?php echo Rateb\App\Core\View::escape($companyName); ?></strong>
                · #<?php echo $cid; ?>
                · <?php echo __('status'); ?>: <?php echo __($companyStatus); ?>
                <?php if (!empty($limits['plan_name'])) { ?>
                · <?php echo __('current_plan'); ?>: <?php echo Rateb\App\Core\View::escape((string) $limits['plan_name']); ?>
                <?php } ?>
            </div>
            <?php if ($canActivateCompany) { ?>
            <form method="post" action="<?php echo rateb_url('admin/companies/' . $cid . '/activate'); ?>" class="mb-0">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="fas fa-play"></i> <?php echo __('bulk_activate'); ?>
                </button>
            </form>
            <?php } ?>
        </div>
        <p class="text-muted small mb-3"><?php echo __('company_permissions_vs_rbac'); ?></p>

        <form method="post"
              action="<?php echo rateb_url($routePrefix . '/' . $cid); ?>"
              id="rateb-company-permissions-form"
              autocomplete="off"
              data-rateb-offline-online-only="1">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">

            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-outline-primary" id="rateb-cp-select-all"
                    onclick="if(window.RatebCompanyPermissions){RatebCompanyPermissions.selectAll();}else{document.querySelectorAll('#rateb-company-permissions-form .rateb-cp-module:not([disabled])').forEach(function(el){el.checked=true;});} return false;">
                    <?php echo __('select_all'); ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="rateb-cp-clear-all"
                    onclick="if(window.RatebCompanyPermissions){RatebCompanyPermissions.clearOptional();}else{document.querySelectorAll('#rateb-company-permissions-form .rateb-cp-module:not([disabled])').forEach(function(el){el.checked=false;});} return false;">
                    <?php echo __('company_permissions_clear_optional'); ?>
                </button>
            </div>

            <div class="row g-2" id="rateb-company-permissions-modules">
                <?php foreach ($moduleCatalog as $modKey => $modLabel) {
                    $isCore = in_array($modKey, $lockedCore, true);
                    $checked = $isCore || in_array($modKey, $selectedModules, true);
                    ?>
                <div class="col-md-4 col-lg-3">
                    <div class="form-check border rounded px-3 py-2 h-100<?php echo $isCore ? ' bg-light' : ''; ?>">
                        <?php if ($isCore) { ?>
                        <input type="hidden" name="modules[]" value="<?php echo Rateb\App\Core\View::escape($modKey); ?>">
                        <input class="form-check-input rateb-cp-module" type="checkbox"
                               value="<?php echo Rateb\App\Core\View::escape($modKey); ?>"
                               id="cp_mod_<?php echo Rateb\App\Core\View::escape($modKey); ?>"
                               checked disabled>
                        <?php } else { ?>
                        <input class="form-check-input rateb-cp-module" type="checkbox" name="modules[]"
                               value="<?php echo Rateb\App\Core\View::escape($modKey); ?>"
                               id="cp_mod_<?php echo Rateb\App\Core\View::escape($modKey); ?>"
                               <?php echo $checked ? ' checked' : ''; ?>>
                        <?php } ?>
                        <label class="form-check-label" for="cp_mod_<?php echo Rateb\App\Core\View::escape($modKey); ?>">
                            <?php echo __(is_string($modLabel) ? $modLabel : $modKey); ?>
                            <?php if ($isCore) { ?>
                            <span class="badge bg-secondary ms-1"><?php echo __('company_permissions_core'); ?></span>
                            <?php } ?>
                            <?php if ($modKey === 'platform_catalog') { ?>
                            <span class="d-block text-muted small mt-1"><?php echo __('company_permissions_platform_catalog_hint'); ?></span>
                            <?php } ?>
                        </label>
                    </div>
                </div>
                <?php } ?>
            </div>

            <div class="mt-4 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary" id="rateb-cp-save">
                    <i class="fas fa-save"></i> <?php echo __('save'); ?>
                </button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
<script src="<?php echo rateb_asset('js/company-permissions.js'); ?>" defer></script>
<script>
(function () {
    var form = document.getElementById('rateb-company-permissions-form');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = document.getElementById('rateb-cp-save');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo Rateb\App\Core\View::escape(__('save')); ?>…';
            }
            try {
                if (window.caches) {
                    ['rateb-erp-ops-pages-v36', 'rateb-erp-coexist-v34', 'rateb-erp-ops-pages-v34', 'rateb-erp-coexist-v32', 'rateb-erp-coexist-v33'].forEach(function (name) {
                        caches.open(name).then(function (cache) {
                            return cache.delete(location.href).catch(function () {});
                        }).catch(function () {});
                    });
                }
            } catch (eCache) { /* ignore */ }
        });
    }
    if (window.RatebCompanyPermissions && typeof window.RatebCompanyPermissions.bind === 'function') {
        window.RatebCompanyPermissions.bind();
    }
})();
</script>
