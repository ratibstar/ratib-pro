<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('purchase_orders'); ?> #<?php echo Rateb\App\Core\View::escape($order['order_no'] ?? ''); ?></div>
    <div class="rateb-card-body">
        <dl class="row mb-4">
            <dt class="col-sm-3"><?php echo __('supplier'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($order['supplier_id'] ?? '—'); ?></dd>
            <dt class="col-sm-3"><?php echo __('order_date'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($order['order_date'] ?? ''); ?></dd>
            <dt class="col-sm-3"><?php echo __('expected_date'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($order['expected_date'] ?? '—'); ?></dd>
            <dt class="col-sm-3"><?php echo __('status'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($order['status'] ?? ''); ?></dd>
            <dt class="col-sm-3"><?php echo __('notes'); ?></dt>
            <dd class="col-sm-9"><?php echo nl2br(Rateb\App\Core\View::escape($order['notes'] ?? '')); ?></dd>
        </dl>
        <h6 class="mb-3"><?php echo __('line_items'); ?></h6>
        <div class="table-responsive">
            <table class="table rateb-table">
                <thead>
                <tr>
                    <th><?php echo __('item_name'); ?></th>
                    <th><?php echo __('description'); ?></th>
                    <th><?php echo __('quantity'); ?></th>
                    <th><?php echo __('delivered'); ?></th>
                    <th><?php echo __('invoiced'); ?></th>
                    <th><?php echo __('unit_of_measure'); ?></th>
                    <th class="text-end"><?php echo __('unit_price'); ?></th>
                    <th><?php echo __('taxes'); ?></th>
                    <th><?php echo __('excluding_tax'); ?></th>
                    <th class="text-end"><?php echo __('line_total'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items ?? [] as $line) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($line['item_name'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($line['description'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($line['quantity'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($line['delivered_qty'] ?? 0); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($line['invoiced_qty'] ?? 0); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape(__('unit_' . ($line['unit'] ?? 'unit'))); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($line['unit_price'] ?? 0), 2); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($line['tax_name'] ?? ''); ?></td>
                    <td><?php echo !empty($line['excluding_tax']) ? __('yes') : __('no'); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($line['total_price'] ?? 0), 2); ?></td>
                </tr>
                <?php } ?>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="9" class="text-end fw-semibold"><?php echo __('subtotal'); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($order['subtotal'] ?? 0), 2); ?></td>
                </tr>
                <tr>
                    <td colspan="9" class="text-end fw-semibold"><?php echo __('tax_amount'); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($order['tax_amount'] ?? 0), 2); ?></td>
                </tr>
                <tr class="table-primary">
                    <td colspan="9" class="text-end fw-bold"><?php echo __('total'); ?></td>
                    <td class="text-end fw-bold"><?php echo number_format((float) ($order['total_amount'] ?? 0), 2); ?></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
