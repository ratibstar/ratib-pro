<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? __('inventory_batches')); ?></span>
        <?php if ($canManage ?? rateb_can_manage_entity('inventory-batches')) { ?>
        <a href="<?php echo rateb_app_url('inventory-batches/create'); ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?php echo __('create'); ?></a>
        <?php } ?>
    </div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => $exportRoute ?? '', 'exportEnabled' => $exportEnabled ?? true]); ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'batch_no', 'label' => 'batch_no'],
                ['name' => 'item_name', 'label' => 'item_name'],
                ['name' => 'quantity', 'label' => 'quantity'],
                ['name' => 'expiry_date', 'label' => 'expiry_date'],
                ['name' => 'warehouse_name', 'label' => 'warehouses'],
            ],
        ]); ?>
    </div>
</div>
