<?php
$order = $order ?? [];
$items = $items ?? [];
$canReceive = in_array((string)($order['status'] ?? ''), ['sent', 'confirmed', 'partial'], true);
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?php echo __('purchase_orders'); ?> #<?php echo Rateb\App\Core\View::escape($order['order_no'] ?? ''); ?></span>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int)($order['id'] ?? 0) . '/edit'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('edit'); ?></a>
            <a href="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int)($order['id'] ?? 0) . '/print'); ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><?php echo __('print'); ?></a>
            <?php if (in_array((string)($order['status'] ?? ''), ['draft', 'confirmed'], true)) { ?>
            <form method="post" action="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int)($order['id'] ?? 0) . '/submit'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-sm btn-success"><?php echo __('send_to_supplier'); ?></button>
            </form>
            <?php } ?>
        </div>
    </div>
    <div class="rateb-card-body">
        <dl class="row mb-4">
            <dt class="col-sm-3"><?php echo __('supplier'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($supplierName ?? '—'); ?></dd>
            <dt class="col-sm-3"><?php echo __('order_date'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($order['order_date'] ?? ''); ?></dd>
            <dt class="col-sm-3"><?php echo __('expected_date'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($order['expected_date'] ?? '—'); ?></dd>
            <dt class="col-sm-3"><?php echo __('status'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape(__($order['status'] ?? '')); ?></dd>
            <dt class="col-sm-3"><?php echo __('currency'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($order['currency'] ?? 'SAR'); ?></dd>
            <?php if ((float)($order['customs_clearance_amount'] ?? 0) > 0
                || !empty($order['customs_declaration_no'])
                || !empty($order['customs_clearance_status'])) { ?>
            <dt class="col-sm-3"><?php echo __('customs_clearance_section'); ?></dt>
            <dd class="col-sm-9">
                <dl class="mb-0">
                    <?php if (!empty($order['customs_declaration_no'])) { ?>
                    <dt class="small text-muted"><?php echo __('customs_declaration_no'); ?></dt>
                    <dd><?php echo Rateb\App\Core\View::escape($order['customs_declaration_no']); ?></dd>
                    <?php } ?>
                    <?php if (!empty($order['customs_clearance_date'])) { ?>
                    <dt class="small text-muted"><?php echo __('customs_clearance_date'); ?></dt>
                    <dd><?php echo Rateb\App\Core\View::escape($order['customs_clearance_date']); ?></dd>
                    <?php } ?>
                    <?php if (!empty($brokerName)) { ?>
                    <dt class="small text-muted"><?php echo __('customs_broker'); ?></dt>
                    <dd><?php echo Rateb\App\Core\View::escape($brokerName); ?></dd>
                    <?php } ?>
                    <?php if (!empty($order['customs_clearance_status'])) { ?>
                    <dt class="small text-muted"><?php echo __('customs_clearance_status'); ?></dt>
                    <dd><?php echo Rateb\App\Core\View::escape(__($order['customs_clearance_status'])); ?></dd>
                    <?php } ?>
                    <?php if ((float)($order['customs_clearance_amount'] ?? 0) > 0) { ?>
                    <dt class="small text-muted"><?php echo __('customs_clearance_costs'); ?></dt>
                    <dd><?php echo number_format((float) $order['customs_clearance_amount'], 2); ?> <?php echo Rateb\App\Core\View::escape($order['currency'] ?? 'SAR'); ?></dd>
                    <?php } ?>
                </dl>
            </dd>
            <?php } ?>
            <dt class="col-sm-3"><?php echo __('notes'); ?></dt>
            <dd class="col-sm-9"><?php echo nl2br(Rateb\App\Core\View::escape($order['notes'] ?? '')); ?></dd>
        </dl>
        <h6 class="mb-3"><?php echo __('line_items'); ?></h6>
        <?php Rateb\App\Core\View::partial('procurement-items-table', ['items' => $items, 'showDeliveryCols' => true, 'order' => $order]); ?>

        <?php if ($canReceive) { ?>
        <div class="rateb-card mt-4">
            <div class="rateb-card-header"><?php echo __('receive_goods'); ?></div>
            <div class="rateb-card-body">
                <form method="post" action="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int)($order['id'] ?? 0) . '/receive'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label"><?php echo __('warehouses'); ?></label>
                            <select name="warehouse_id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($warehouses ?? [] as $wh) { ?>
                                <option value="<?php echo (int) $wh['id']; ?>"<?php echo (int)($order['warehouse_id'] ?? 0) === (int)$wh['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($wh['name'] ?? ''); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table rateb-table">
                            <thead><tr><th><?php echo __('item_name'); ?></th><th><?php echo __('quantity'); ?></th><th><?php echo __('delivered'); ?></th><th><?php echo __('receive_now'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $line) {
                                $remaining = max(0, (float)($line['quantity'] ?? 0) - (float)($line['delivered_qty'] ?? 0));
                                if ($remaining <= 0) { continue; }
                                ?>
                            <tr>
                                <td><?php echo Rateb\App\Core\View::escape($line['item_name'] ?? ''); ?></td>
                                <td><?php echo Rateb\App\Core\View::escape($line['quantity'] ?? 0); ?></td>
                                <td><?php echo Rateb\App\Core\View::escape($line['delivered_qty'] ?? 0); ?></td>
                                <td><input type="number" step="0.001" min="0" max="<?php echo $remaining; ?>" name="receive_qty[<?php echo (int)($line['id'] ?? 0); ?>]" class="form-control form-control-sm" value="<?php echo $remaining; ?>"></td>
                            </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary mt-2"><?php echo __('receive_goods'); ?></button>
                </form>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
<?php if (!empty($docBarcode)) {
    Rateb\App\Core\View::partial('document-barcode-label', ['docBarcode' => $docBarcode]);
} ?>
