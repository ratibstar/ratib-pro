<?php
/** Super-admin company picker for ops modules (assets, inventory, accounting, …) */
if (!rateb_is_super_admin()) {
    return;
}
$companies = $companies ?? (new \Rateb\App\Models\Company())->all(200, 0);
$selectedId = (int) ($selectedCompanyId ?? rateb_resolve_ops_company_id());
$cpMode = defined('RATEB_CP_MODE') && RATEB_CP_MODE;
$cpRoute = $cpMode ? rateb_current_erp_route() : rateb_current_erp_route('');
$formAction = '';
if ($cpMode && defined('RATEB_CP_APP_URL')) {
    $formAction = (string) RATEB_CP_APP_URL;
} elseif (function_exists('rateb_url')) {
    $route = rateb_current_erp_route('');
    $formAction = $route !== '' ? rateb_url($route) : '';
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
        <form method="get" class="row g-2 align-items-end"<?php
            if ($formAction !== '') {
                echo ' action="' . Rateb\App\Core\View::escape($formAction) . '"';
            }
        ?>>
            <?php if ($cpMode && $cpRoute !== '') { ?>
            <input type="hidden" name="route" value="<?php echo Rateb\App\Core\View::escape($cpRoute); ?>">
            <?php } ?>
            <div class="col-md-5">
                <label class="form-label mb-1"><?php echo __('select_company'); ?></label>
                <select class="form-select" name="company_id" required onchange="this.form.submit()">
                    <option value=""><?php echo __('select_company'); ?>…</option>
                    <?php foreach ($companies as $c) { ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo $selectedId === (int) $c['id'] ? ' selected' : ''; ?>>
                        <?php echo Rateb\App\Core\View::escape($c['name'] ?? ''); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-7">
                <p class="text-muted small mb-0"><?php echo __('ops_company_select_help'); ?></p>
            </div>
        </form>
        <?php } ?>
    </div>
</div>
<?php if ($selectedId < 1 && $companies !== []) { ?>
<div class="alert alert-warning"><?php echo __('select_company_ops'); ?></div>
<?php } ?>
