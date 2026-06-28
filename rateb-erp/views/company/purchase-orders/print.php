<?php
$order = $order ?? [];
$items = $items ?? [];
$currency = (string) ($order['currency'] ?? 'SAR');
?>
<div class="rateb-po-print-header">
    <h2 class="mb-1"><?php echo __('purchase_orders'); ?></h2>
    <div class="row">
        <div class="col-md-6">
            <strong><?php echo __('order_no'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($order['order_no'] ?? ''); ?><br>
            <strong><?php echo __('order_date'); ?>:</strong> <?php echo Rateb\App\Core\View::formatDate($order['order_date'] ?? ''); ?><br>
            <strong><?php echo __('supplier'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($supplierName ?? ''); ?>
        </div>
        <div class="col-md-6 text-end">
            <?php if (!empty($docBarcode['qr_image_url'])) { ?>
            <img src="<?php echo Rateb\App\Core\View::escape($docBarcode['qr_image_url']); ?>" alt="QR" width="100" height="100">
            <?php } ?>
        </div>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-bordered rateb-po-print-table">
        <thead>
        <tr>
            <th><?php echo __('description'); ?></th>
            <th><?php echo __('quantity'); ?></th>
            <th class="text-end"><?php echo __('unit_price'); ?></th>
            <th><?php echo __('taxes'); ?></th>
            <th class="text-end"><?php echo __('amount'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $line) { ?>
        <tr>
            <td><?php echo Rateb\App\Core\View::escape(($line['sku'] ?? '') !== '' ? '[' . $line['sku'] . '] ' : ''); ?><?php echo Rateb\App\Core\View::escape($line['item_name'] ?? ''); ?></td>
            <td><?php echo number_format((float)($line['quantity'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape(__('unit_' . ($line['unit'] ?? 'each'))); ?></td>
            <td class="text-end"><?php echo number_format((float)($line['unit_price'] ?? 0), 2); ?></td>
            <td><?php echo Rateb\App\Core\View::escape($line['tax_name'] ?? ''); ?></td>
            <td class="text-end"><?php echo number_format((float)($line['total_price'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></td>
        </tr>
        <?php } ?>
        </tbody>
        <tfoot>
        <tr><td colspan="4" class="text-end"><?php echo __('subtotal'); ?></td><td class="text-end"><?php echo number_format((float)($order['subtotal'] ?? 0), 2); ?></td></tr>
        <tr><td colspan="4" class="text-end"><?php echo __('tax_amount'); ?></td><td class="text-end"><?php echo number_format((float)($order['tax_amount'] ?? 0), 2); ?></td></tr>
        <tr class="rateb-po-print-total"><td colspan="4" class="text-end"><?php echo __('total'); ?></td><td class="text-end"><?php echo number_format((float)($order['total_amount'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></td></tr>
        </tfoot>
    </table>
</div>
