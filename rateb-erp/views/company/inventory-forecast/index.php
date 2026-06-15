<?php
/** @var array<int,array<string,mixed>> $rows */
?>
<h1><?php echo __('inventory_forecast'); ?></h1>
<p class="text-muted"><?php echo __('inventory_forecast_hint'); ?></p>
<div class="rateb-table-wrap">
<table class="table table-sm rateb-table mb-0">
    <thead><tr><th><?php echo __('item_name'); ?></th><th><?php echo __('quantity'); ?></th><th><?php echo __('reorder_level'); ?></th><th><?php echo __('usage_90d'); ?></th><th><?php echo __('warehouse'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['item_name'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['quantity'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['reorder_level'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['consumed_90d'] ?? '0')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['warehouse_name'] ?? '')); ?></td>
        </tr>
    <?php } ?>
    </tbody>
</table>
</div>
