<?php
/** Super-admin company picker for ops modules (assets, inventory, accounting, …) */
if (!rateb_is_super_admin()) {
    return;
}
$companies = $companies ?? (function_exists('rateb_ops_companies_list') ? rateb_ops_companies_list() : (new \Rateb\App\Models\Company())->all(200, 0));
$selectedId = (int) ($selectedCompanyId ?? rateb_resolve_ops_company_id());
$cpMode = defined('RATEB_CP_MODE') && RATEB_CP_MODE;
$cpRoute = $cpMode ? rateb_current_erp_route() : rateb_current_erp_route('');
// create/list stay put; only /{id}/edit|/show leave for the module list (record rebinds tenant).
$pickerRoute = function_exists('rateb_ops_company_picker_target_route')
    ? rateb_ops_company_picker_target_route($cpRoute !== '' ? $cpRoute : null)
    : $cpRoute;
$formAction = '';
if ($cpMode && defined('RATEB_CP_APP_URL')) {
    $formAction = (string) RATEB_CP_APP_URL;
} elseif (function_exists('rateb_url') && $pickerRoute !== '') {
    $formAction = rateb_url($pickerRoute);
}
$selectedName = '';
foreach ($companies as $c) {
    if ((int) ($c['id'] ?? 0) === $selectedId) {
        $selectedName = (string) ($c['name'] ?? '');
        break;
    }
}
?>
<div class="rateb-card mb-3 rateb-ops-company-select">
    <div class="rateb-card-body py-3">
        <?php if ($selectedId > 0 && $selectedName !== '') { ?>
        <div class="alert alert-info py-2 mb-3 mb-md-2">
            <i class="fas fa-building me-1"></i>
            <strong><?php echo __('active_company'); ?>:</strong>
            <?php echo Rateb\App\Core\View::escape($selectedName); ?>
        </div>
        <?php } ?>
        <?php if ($companies === []) { ?>
        <div class="alert alert-warning mb-0">
            <?php echo __('no_companies_for_ops'); ?>
            <a href="<?php echo rateb_url('admin/companies/create'); ?>" class="alert-link"><?php echo __('companies'); ?></a>
        </div>
        <?php } else { ?>
        <form method="get" class="row g-2 align-items-end rateb-ops-company-form" data-rateb-full-nav="1"<?php
            if ($formAction !== '') {
                echo ' action="' . Rateb\App\Core\View::escape($formAction) . '"';
            }
        ?>>
            <?php if ($cpMode && $pickerRoute !== '') { ?>
            <input type="hidden" name="route" value="<?php echo Rateb\App\Core\View::escape($pickerRoute); ?>">
            <?php } ?>
            <?php /* Bypass SW stale ops HTML for tenant switches (must hit live PHP). */ ?>
            <input type="hidden" name="rateb_live" value="1">
            <div class="col-md-5">
                <label class="form-label mb-1"><?php echo __('select_company'); ?></label>
                <select class="form-select" name="company_id" data-rateb-ops-company-pick="1" onchange="(function(s){try{var f=s.form;var base=f.getAttribute('action')||(location.origin+location.pathname);var u=new URL(base,location.origin);u.search='';u.hash='';u.searchParams.set('company_id',String(s.value||'0'));u.searchParams.set('rateb_live','1');location.assign(u.pathname+u.search);}catch(e){f&&f.submit&&f.submit();}})(this)">
                    <option value="0"<?php echo $selectedId < 1 ? ' selected' : ''; ?>>
                        <?php echo __('ops_company_platform_mode'); ?>
                    </option>
                    <?php foreach ($companies as $c) { ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo $selectedId === (int) $c['id'] ? ' selected' : ''; ?>>
                        <?php echo Rateb\App\Core\View::escape($c['name'] ?? ''); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-7">
                <p class="text-muted small mb-1"><?php echo __('ops_company_select_help'); ?></p>
                <?php if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) { ?>
                <p class="small mb-0">
                    <a href="<?php echo rateb_url('admin/agency-updates' . ($selectedId > 0 ? '?company_id=' . (int) $selectedId : '')); ?>" class="text-decoration-none">
                        <i class="fas fa-cloud-upload-alt me-1"></i><?php echo __('agency_erp_push_after_ops'); ?>
                    </a>
                </p>
                <?php } ?>
            </div>
        </form>
        <?php } ?>
    </div>
</div>
<?php if ($selectedId < 1 && $companies !== []) { ?>
<div class="alert alert-secondary py-2"><?php echo __('ops_company_platform_mode_help'); ?></div>
<?php } ?>
