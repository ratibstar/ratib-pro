<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $plans */
/** @var array<int, array<string, mixed>> $planPresets */
/** @var array<string, string> $moduleCatalog */
/** @var array<int, string> $selectedModules */
/** @var array<string, mixed>|null $limits */
/** @var string $companyAdminLogin */
/** @var string $companyLoginUrl */
/** @var string $companyAdminUrl */
/** @var array<string, mixed>|null $linkedAgency */
/** @var bool $agencyPortalMode */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
$userLimitVal = (int) ($item['user_limit'] ?? 0);
if ($userLimitVal < 1) {
    $userLimitVal = (int) ($limits['user_limit'] ?? 10);
}
$storageLimitVal = (int) ($item['storage_limit_mb'] ?? 0);
if ($storageLimitVal < 1) {
    $storageLimitVal = (int) ($limits['storage_limit_mb'] ?? 1024);
}
$companyAdminLogin = (string) ($companyAdminLogin ?? '');
$companyLoginUrl = (string) ($companyLoginUrl ?? '');
$companyAdminUrl = (string) ($companyAdminUrl ?? '');
$linkedAgency = (isset($linkedAgency) && is_array($linkedAgency)) ? $linkedAgency : null;
$agencyPortalMode = !empty($agencyPortalMode);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <?php if ($linkedAgency) { ?>
        <div class="alert alert-secondary mb-3">
            <strong><?php echo __('company_agency_mirror_title'); ?></strong>
            #<?php echo (int) ($linkedAgency['id'] ?? 0); ?>
            — <?php echo Rateb\App\Core\View::escape((string) ($linkedAgency['name'] ?? '')); ?>
            <?php
            $bannerSite = trim((string) ($linkedAgency['site_url'] ?? ''));
            if ($bannerSite !== '') { ?>
            · <a href="<?php echo Rateb\App\Core\View::escape($bannerSite); ?>" target="_blank" rel="noopener"><?php echo Rateb\App\Core\View::escape($bannerSite); ?></a>
            <?php } ?>
            <div class="small mt-1 mb-0"><?php echo __('company_agency_mirror_help'); ?></div>
        </div>
        <?php } ?>
        <form method="post" action="<?php echo $action; ?>" id="rateb-company-form" data-rateb-offline-writable="1">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <input type="hidden" name="sync_from_plan" id="rateb-sync-from-plan" value="0">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('name'); ?></label>
                    <input class="form-control" type="text" name="name" value="<?php echo Rateb\App\Core\View::escape($item['name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('slug'); ?></label>
                    <input class="form-control" type="text" name="slug" id="rateb-company-slug" value="<?php echo Rateb\App\Core\View::escape($item['slug'] ?? ''); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label"><?php echo $agencyPortalMode ? __('company_agency_portal_links') : __('company_login_link'); ?></label>
                    <?php if ($agencyPortalMode) { ?>
                    <p class="form-text mb-2"><?php echo __('company_agency_portal_help'); ?></p>
                    <?php if ($companyLoginUrl !== '') { ?>
                    <label class="form-label small mb-1"><?php echo __('company_agency_login'); ?></label>
                    <div class="input-group input-group-sm mb-2">
                        <input class="form-control" type="text" value="<?php echo Rateb\App\Core\View::escape($companyLoginUrl); ?>" readonly>
                        <a class="btn btn-outline-secondary" href="<?php echo Rateb\App\Core\View::escape($companyLoginUrl); ?>" target="_blank" rel="noopener"><?php echo __('open_company_portal'); ?></a>
                    </div>
                    <?php } ?>
                    <?php if ($companyAdminUrl !== '') { ?>
                    <label class="form-label small mb-1"><?php echo __('company_agency_admin'); ?></label>
                    <div class="input-group input-group-sm">
                        <input class="form-control" type="text" value="<?php echo Rateb\App\Core\View::escape($companyAdminUrl); ?>" readonly>
                        <a class="btn btn-outline-primary" href="<?php echo Rateb\App\Core\View::escape($companyAdminUrl); ?>" target="_blank" rel="noopener"><?php echo __('company_agency_open_admin'); ?></a>
                    </div>
                    <?php } ?>
                    <?php } else { ?>
                    <p class="form-text mb-1"><?php echo __('company_login_link_help'); ?></p>
                    <?php if ($companyLoginUrl !== '') { ?>
                    <div class="input-group input-group-sm">
                        <input class="form-control" type="text" id="rateb-company-login-url" value="<?php echo Rateb\App\Core\View::escape($companyLoginUrl); ?>" readonly>
                        <a class="btn btn-outline-secondary" href="<?php echo Rateb\App\Core\View::escape($companyLoginUrl); ?>" target="_blank" rel="noopener"><?php echo __('open_company_portal'); ?></a>
                    </div>
                    <?php } else { ?>
                    <p class="form-text text-muted mb-0"><?php echo __('company_login_link_after_slug'); ?></p>
                    <?php } ?>
                    <?php } ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('email'); ?></label>
                    <input class="form-control" type="email" name="email" value="<?php echo Rateb\App\Core\View::escape($item['email'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('phone'); ?></label>
                    <input class="form-control" type="text" name="phone" value="<?php echo Rateb\App\Core\View::escape($item['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <?php
                    $currentStatus = (string) ($item['status'] ?? 'pending');
                    $statusBadge = match ($currentStatus) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        default => 'info',
                    };
                    ?>
                    <div class="form-control-plaintext">
                        <span class="badge bg-<?php echo $statusBadge; ?>"><?php echo __($currentStatus); ?></span>
                    </div>
                    <p class="form-text mb-0"><?php echo __('company_status_oversight_hint'); ?></p>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('plans'); ?></label>
                    <select class="form-select" name="plan_id" id="rateb-company-plan">
                        <option value="">—</option>
                        <?php foreach ($plans as $plan) { ?>
                        <option value="<?php echo (int) $plan['id']; ?>"<?php echo (int) ($item['plan_id'] ?? 0) === (int) $plan['id'] ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($plan['name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <p class="form-text mb-0"><?php echo __('company_plan_modules_sync_hint'); ?></p>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('user_limit'); ?></label>
                    <input class="form-control" type="number" name="user_limit" min="1" value="<?php echo Rateb\App\Core\View::escape((string) $userLimitVal); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('storage_limit_mb'); ?></label>
                    <input class="form-control" type="number" name="storage_limit_mb" min="128" value="<?php echo Rateb\App\Core\View::escape((string) $storageLimitVal); ?>">
                </div>
            </div>

            <div class="mt-4 p-3 border rounded" id="rateb-company-admin-login">
                <h3 class="h6 mb-2"><?php echo __('company_admin_login_title'); ?></h3>
                <p class="form-text mb-3"><?php echo $isEdit ? __('company_admin_login_edit_help') : __('company_admin_login_help'); ?></p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><?php echo __('company_admin_username'); ?></label>
                        <input class="form-control" type="text" name="admin_username" value="<?php echo Rateb\App\Core\View::escape($companyAdminLogin); ?>" required autocomplete="off"
                               placeholder="<?php echo Rateb\App\Core\View::escape(__('company_admin_username_ph')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo __('company_admin_password'); ?></label>
                        <input class="form-control" type="text" name="admin_password" value="" <?php echo $isEdit ? '' : 'required'; ?> autocomplete="new-password" minlength="6"
                               placeholder="<?php echo Rateb\App\Core\View::escape($isEdit ? __('company_admin_password_edit_ph') : __('company_admin_password_ph')); ?>">
                    </div>
                </div>
            </div>

            <div class="mt-4" id="rateb-company-modules">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h3 class="h6 mb-0"><?php echo __('plan_modules'); ?> / <?php echo __('company_permissions'); ?></h3>
                    <?php if ($isEdit) { ?>
                    <a href="<?php echo rateb_url('admin/company-permissions/' . (int) $item['id']); ?>" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-sliders"></i> <?php echo __('company_permissions_manage'); ?>
                    </a>
                    <?php } ?>
                </div>
                <p class="form-text mb-2"><?php echo __('company_plan_modules_tenant_help'); ?></p>
                <div class="row g-2">
                    <?php foreach ($moduleCatalog as $modKey => $modLabel) { ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="modules[]" value="<?php echo Rateb\App\Core\View::escape($modKey); ?>" id="mod_<?php echo Rateb\App\Core\View::escape($modKey); ?>"
                                <?php echo in_array($modKey, $selectedModules, true) ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="mod_<?php echo Rateb\App\Core\View::escape($modKey); ?>">
                                <?php echo __(is_string($modLabel) ? $modLabel : $modKey); ?>
                            </label>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <?php if ($limits) { ?>
            <div class="alert alert-secondary mt-3 mb-0">
                <strong><?php echo __('current_plan'); ?>:</strong>
                <?php echo Rateb\App\Core\View::escape($limits['plan_name'] ?? '—'); ?>
                · <?php echo __('user_limit'); ?>: <?php echo (int) $limits['user_limit']; ?>
                · <?php echo __('storage_limit_mb'); ?>: <?php echo (int) $limits['storage_limit_mb']; ?>
            </div>
            <?php } ?>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
<script type="application/json" id="rateb-company-plan-presets"><?php echo json_encode($planPresets ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<script src="<?php echo rateb_asset('js/company-plan-form.js'); ?>"></script>
