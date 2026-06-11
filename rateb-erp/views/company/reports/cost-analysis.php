<?php $d = $data ?? []; ?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <?php Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => $exportRoute ?? '']); ?>
    </div>
    <div class="rateb-card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo number_format((float) ($d['procurement_spend'] ?? 0), 2); ?></div><div class="rateb-widget-label"><?php echo __('procurement_spend'); ?></div></div></div>
            <div class="col-md-4"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo number_format((float) ($d['inventory_value'] ?? 0), 2); ?></div><div class="rateb-widget-label"><?php echo __('inventory_value'); ?></div></div></div>
            <div class="col-md-4"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo number_format((float) ($d['asset_value'] ?? 0), 2); ?></div><div class="rateb-widget-label"><?php echo __('asset_value'); ?></div></div></div>
        </div>
        <h6><?php echo __('spend_by_supplier'); ?></h6>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $d['po_by_supplier'] ?? [],
            'columns' => [
                ['name' => 'supplier_name', 'label' => 'suppliers'],
                ['name' => 'total', 'label' => 'total'],
            ],
        ]); ?>
    </div>
</div>
