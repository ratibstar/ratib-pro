<?php Rateb\App\Core\View::partial('admin-company-portal-banner'); ?>
<div class="row g-3">
    <?php if (!empty($companies)) { ?>
    <div class="col-12">
        <form method="get" class="row g-2">
            <div class="col-md-4">
                <select class="form-select" name="company_id" onchange="this.form.submit()">
                    <option value=""><?php echo __('all_companies'); ?></option>
                    <?php foreach ($companies as $c) { ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo (int)($_GET['company_id'] ?? 0) === (int)$c['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($c['name'] ?? ''); ?></option>
                    <?php } ?>
                </select>
            </div>
        </form>
    </div>
    <?php } ?>
    <div class="col-12">
        <div class="rateb-card mb-3">
            <div class="rateb-card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><?php echo __('inventory_value'); ?>: <strong><?php echo number_format((float)($total_value ?? 0), 2); ?></strong></span>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('inventory'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', [
                    'title' => '',
                    'items' => $items ?? [],
                    'fields' => $itemFields ?? [],
                    'csrf' => $csrf,
                    'routePrefix' => 'admin/inventory',
                    'bulkEnabled' => false,
                    'createEnabled' => false,
                    'actionsEnabled' => false,
                ]); ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('warehouses'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', [
                    'title' => '',
                    'items' => $warehouses ?? [],
                    'fields' => $warehouseFields ?? [],
                    'csrf' => $csrf,
                    'routePrefix' => 'admin/inventory',
                    'bulkEnabled' => false,
                    'createEnabled' => false,
                    'actionsEnabled' => false,
                ]); ?>
            </div>
        </div>
    </div>
</div>
