<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <?php Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => $exportRoute ?? '', 'exportEnabled' => $exportEnabled ?? true, 'inline' => true]); ?>
    </div>
    <div class="rateb-card-body">
        <div class="rateb-widget mb-4"><div class="rateb-widget-value"><?php echo number_format((float) ($total_value ?? 0), 2); ?></div><div class="rateb-widget-label"><?php echo __('total_inventory_value'); ?></div></div>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $rows ?? [],
            'columns' => [
                ['name' => 'item_name', 'label' => 'item_name'],
                ['name' => 'warehouse_name', 'label' => 'warehouses'],
                ['name' => 'quantity', 'label' => 'quantity'],
                ['name' => 'unit_cost', 'label' => 'unit_price'],
                ['name' => 'line_value', 'label' => 'value'],
            ],
        ]); ?>
    </div>
</div>
