<?php
/** Super-admin company picker for ops modules (accounting, etc.) */
if (!rateb_is_super_admin()) {
    return;
}
$companies = $companies ?? (new \Rateb\App\Models\Company())->all(200, 0);
$selectedId = (int) ($selectedCompanyId ?? rateb_resolve_ops_company_id());
$currentPath = defined('RATEB_CP_ROUTE') ? (string) RATEB_CP_ROUTE : trim((string) ($_GET['route'] ?? ''), '/');
if ($currentPath === '') {
    $currentPath = 'accounting';
}
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="route" value="<?php echo Rateb\App\Core\View::escape($currentPath); ?>">
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
    </div>
</div>
<?php if ($selectedId < 1) { ?>
<div class="alert alert-warning"><?php echo __('select_company_ops'); ?></div>
<?php } ?>
