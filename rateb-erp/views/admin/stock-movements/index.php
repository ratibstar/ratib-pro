<?php if (!empty($companies)) { ?>
<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <select class="form-select" name="company_id" onchange="this.form.submit()">
            <option value=""><?php echo __('all_companies'); ?></option>
            <?php foreach ($companies as $c) { ?>
            <option value="<?php echo (int) $c['id']; ?>"<?php echo (int)($_GET['company_id'] ?? 0) === (int)$c['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($c['name'] ?? ''); ?></option>
            <?php } ?>
        </select>
    </div>
</form>
<?php } ?>
<?php Rateb\App\Core\View::partial('crud-index', [
    'title' => $title ?? __('stock_movements'),
    'items' => $items ?? [],
    'fields' => [
        ['name' => 'movement_no', 'label' => 'movement_no'],
        ['name' => 'movement_type', 'label' => 'movement_type'],
        ['name' => 'item_name', 'label' => 'item_name'],
        ['name' => 'quantity', 'label' => 'quantity'],
        ['name' => 'warehouse_name', 'label' => 'warehouses'],
        ['name' => 'created_at', 'label' => 'created_at'],
    ],
    'csrf' => $csrf,
    'routePrefix' => 'admin/stock-movements',
    'exportRoute' => rateb_url('admin/stock-movements/export'),
    'exportEnabled' => true,
    'bulkEnabled' => false,
    'createEnabled' => false,
    'actionsEnabled' => false,
]); ?>
