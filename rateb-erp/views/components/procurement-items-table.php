<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<string, mixed> $order */
$items = $items ?? [];
$order = $order ?? [];
$showDeliveryCols = !empty($showDeliveryCols);
$currency = (string) ($order['currency'] ?? 'SAR');
?>
<div class="table-responsive">
    <table class="table rateb-table">
        <thead>
        <tr>
            <th><?php echo __('item_name'); ?></th>
            <th><?php echo __('description'); ?></th>
            <th><?php echo __('quantity'); ?></th>
            <?php if ($showDeliveryCols) { ?>
            <th><?php echo __('delivered'); ?></th>
            <th><?php echo __('invoiced'); ?></th>
            <?php } ?>
            <th><?php echo __('unit_of_measure'); ?></th>
            <th class="text-end"><?php echo __('unit_price'); ?></th>
            <th><?php echo __('taxes'); ?></th>
            <th class="text-end"><?php echo __('line_total'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $line) { ?>
        <tr>
            <td><?php echo Rateb\App\Core\View::escape($line['item_name'] ?? ''); ?></td>
            <td><?php echo Rateb\App\Core\View::escape($line['description'] ?? ''); ?></td>
            <td><?php echo Rateb\App\Core\View::escape($line['quantity'] ?? ''); ?></td>
            <?php if ($showDeliveryCols) { ?>
            <td><?php echo Rateb\App\Core\View::escape($line['delivered_qty'] ?? 0); ?></td>
            <td><?php echo Rateb\App\Core\View::escape($line['invoiced_qty'] ?? 0); ?></td>
            <?php } ?>
            <td><?php echo Rateb\App\Core\View::escape(__('unit_' . ($line['unit'] ?? 'unit'))); ?></td>
            <td class="text-end"><?php echo number_format((float) ($line['unit_price'] ?? 0), 2); ?></td>
            <td><?php echo Rateb\App\Core\View::escape($line['tax_name'] ?? ''); ?></td>
            <td class="text-end"><?php echo number_format((float) ($line['total_price'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></td>
        </tr>
        <?php } ?>
        </tbody>
        <?php if (!empty($order)) { ?>
        <tfoot>
        <tr>
            <td colspan="<?php echo $showDeliveryCols ? 8 : 6; ?>" class="text-end fw-semibold"><?php echo __('subtotal'); ?></td>
            <td class="text-end"><?php echo number_format((float) ($order['subtotal'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></td>
        </tr>
        <?php if ((float)($order['discount_amount'] ?? 0) > 0) { ?>
        <tr>
            <td colspan="<?php echo $showDeliveryCols ? 8 : 6; ?>" class="text-end"><?php echo __('discount'); ?></td>
            <td class="text-end">-<?php echo number_format((float) $order['discount_amount'], 2); ?></td>
        </tr>
        <?php } ?>
        <?php if ((float)($order['shipping_amount'] ?? 0) > 0) { ?>
        <tr>
            <td colspan="<?php echo $showDeliveryCols ? 8 : 6; ?>" class="text-end"><?php echo __('shipping'); ?></td>
            <td class="text-end"><?php echo number_format((float) $order['shipping_amount'], 2); ?></td>
        </tr>
        <?php } ?>
        <tr>
            <td colspan="<?php echo $showDeliveryCols ? 8 : 6; ?>" class="text-end fw-semibold"><?php echo __('tax_amount'); ?></td>
            <td class="text-end"><?php echo number_format((float) ($order['tax_amount'] ?? 0), 2); ?></td>
        </tr>
        <tr class="table-primary">
            <td colspan="<?php echo $showDeliveryCols ? 8 : 6; ?>" class="text-end fw-bold"><?php echo __('total'); ?></td>
            <td class="text-end fw-bold"><?php echo number_format((float) ($order['total_amount'] ?? $order['total_estimated'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></td>
        </tr>
        </tfoot>
        <?php } ?>
    </table>
</div>
