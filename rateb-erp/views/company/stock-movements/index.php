<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('stock_movements')); ?></div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('export-toolbar', [
            'exportRoute' => rateb_app_url('stock-movements/export'),
            'exportEnabled' => $exportEnabled ?? true,
        ]); ?>
        <?php if ($canManage ?? rateb_can_manage_entity('stock-movements')) { ?>
        <form method="post" action="<?php echo rateb_app_url('stock-movements'); ?>" class="row g-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('inventory'); ?></label>
                <select class="form-select" name="inventory_id" required>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($inventory ?? [] as $inv) { ?>
                    <option value="<?php echo (int) $inv['id']; ?>"><?php echo Rateb\App\Core\View::escape($inv['item_name'] ?? ''); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('movement_type'); ?></label>
                <select class="form-select" name="movement_type">
                    <option value="in">in</option>
                    <option value="out">out</option>
                    <option value="transfer">transfer</option>
                    <option value="adjustment">adjustment</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('quantity'); ?></label>
                <input class="form-control" type="number" step="0.001" min="0.001" name="quantity" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('warehouses'); ?></label>
                <select class="form-select" name="warehouse_id">
                    <option value="">—</option>
                    <?php foreach ($warehouses ?? [] as $wh) { ?>
                    <option value="<?php echo (int) $wh['id']; ?>"><?php echo Rateb\App\Core\View::escape($wh['name'] ?? ''); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo __('notes'); ?></label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('crud-index', [
            'title' => '',
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
            'routePrefix' => rateb_app_route('stock-movements'),
            'permissionResource' => 'stock-movements',
            'bulkEnabled' => $canManage ?? rateb_can_manage_entity('stock-movements'),
            'createEnabled' => false,
            'actionsEnabled' => false,
            'documentEntityType' => '',
        ]); ?>
    </div>
</div>
