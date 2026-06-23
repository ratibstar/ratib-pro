<?php
$order = $order ?? [];
$items = $items ?? [];
$purchaseInvoice = $purchaseInvoice ?? null;
$canReceive = in_array((string) ($order['status'] ?? ''), ['sent', 'confirmed', 'partial'], true);
$orderNo = (string) ($order['order_no'] ?? '');
$status = (string) ($order['status'] ?? '');
$currency = (string) ($order['currency'] ?? 'SAR');
$inv = is_array($purchaseInvoice) ? $purchaseInvoice : [];
$hasCustoms = (float) ($inv['customs_clearance_amount'] ?? 0) > 0
    || (float) ($inv['shipping_amount'] ?? 0) > 0
    || !empty($inv['customs_declaration_no'])
    || !empty($inv['customs_clearance_status']);

$statusClass = 'rateb-po-status--draft';
if (in_array($status, ['sent', 'confirmed'], true)) {
    $statusClass = 'rateb-po-status--sent';
} elseif ($status === 'partial') {
    $statusClass = 'rateb-po-status--partial';
} elseif (in_array($status, ['received', 'approved'], true)) {
    $statusClass = 'rateb-po-status--received';
} elseif (in_array($status, ['cancelled', 'rejected'], true)) {
    $statusClass = 'rateb-po-status--cancelled';
}

$customsStatus = (string) ($inv['customs_clearance_status'] ?? '');
$customsStatusClass = 'rateb-po-status--draft';
if ($customsStatus === 'customs_cleared') {
    $customsStatusClass = 'rateb-po-status--received';
} elseif ($customsStatus === 'customs_in_progress') {
    $customsStatusClass = 'rateb-po-status--sent';
} elseif (in_array($customsStatus, ['customs_held', 'customs_rejected'], true)) {
    $customsStatusClass = 'rateb-po-status--cancelled';
} elseif ($customsStatus === 'customs_pending') {
    $customsStatusClass = 'rateb-po-status--partial';
}
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/procurement-show.css'); ?>">

<div class="rateb-po-show">
    <div class="rateb-po-show-hero mb-3">
        <div class="rateb-po-show-hero-inner">
            <div>
                <div class="rateb-po-show-id"><?php echo Rateb\App\Core\View::escape($orderNo); ?></div>
                <h1 class="rateb-po-show-title"><?php echo __('purchase_orders'); ?></h1>
                <?php if ($status !== '') { ?>
                <span class="rateb-po-status <?php echo Rateb\App\Core\View::escape($statusClass); ?>">
                    <?php echo Rateb\App\Core\View::escape(__($status)); ?>
                </span>
                <?php } ?>
            </div>
            <div class="rateb-po-show-actions">
                <a href="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($order['id'] ?? 0) . '/invoice'); ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-file-invoice-dollar"></i> <?php echo __('manage_purchase_invoice'); ?>
                </a>
                <a href="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($order['id'] ?? 0) . '/edit'); ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
                </a>
                <a href="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($order['id'] ?? 0) . '/print'); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                    <i class="fas fa-print"></i> <?php echo __('print'); ?>
                </a>
                <?php if (in_array($status, ['draft', 'confirmed'], true)) { ?>
                <form method="post" action="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($order['id'] ?? 0) . '/submit'); ?>" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-paper-plane"></i> <?php echo __('send_to_supplier'); ?>
                    </button>
                </form>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="rateb-card mb-3">
                <div class="rateb-card-header">
                    <i class="fas fa-file-invoice me-1"></i> <?php echo __('order_details'); ?>
                </div>
                <div class="rateb-card-body">
                    <div class="rateb-kv-grid">
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('supplier'); ?></span>
                            <span class="rateb-kv-value"><?php echo Rateb\App\Core\View::escape($supplierName ?? '—'); ?></span>
                        </div>
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('order_date'); ?></span>
                            <span class="rateb-kv-value rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($order['order_date'] ?? '—'); ?></span>
                        </div>
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('expected_date'); ?></span>
                            <span class="rateb-kv-value rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($order['expected_date'] ?? '—'); ?></span>
                        </div>
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('currency'); ?></span>
                            <span class="rateb-kv-value rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($currency); ?></span>
                        </div>
                    </div>
                    <?php if (trim((string) ($order['notes'] ?? '')) !== '') { ?>
                    <div class="rateb-po-notes">
                        <span class="rateb-kv-label"><?php echo __('notes'); ?></span>
                        <div class="rateb-kv-value mt-1"><?php echo nl2br(Rateb\App\Core\View::escape($order['notes'] ?? '')); ?></div>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <?php if ($hasCustoms) { ?>
            <div class="rateb-card rateb-po-customs-panel mb-3">
                <div class="rateb-card-header">
                    <i class="fas fa-passport me-1"></i> <?php echo __('customs_clearance_section'); ?>
                </div>
                <div class="rateb-card-body">
                    <div class="rateb-kv-grid">
                        <?php if (!empty($inv['customs_declaration_no'])) { ?>
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('customs_declaration_no'); ?></span>
                            <span class="rateb-kv-value"><?php echo Rateb\App\Core\View::escape($inv['customs_declaration_no']); ?></span>
                        </div>
                        <?php } ?>
                        <?php if (!empty($inv['customs_clearance_date'])) { ?>
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('customs_clearance_date'); ?></span>
                            <span class="rateb-kv-value rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($inv['customs_clearance_date']); ?></span>
                        </div>
                        <?php } ?>
                        <?php if (!empty($brokerName)) { ?>
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('customs_broker'); ?></span>
                            <span class="rateb-kv-value"><?php echo Rateb\App\Core\View::escape($brokerName); ?></span>
                        </div>
                        <?php } ?>
                        <?php if ($customsStatus !== '') { ?>
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('customs_clearance_status'); ?></span>
                            <span class="rateb-kv-value">
                                <span class="rateb-po-status <?php echo Rateb\App\Core\View::escape($customsStatusClass); ?>">
                                    <?php echo Rateb\App\Core\View::escape(__($customsStatus)); ?>
                                </span>
                            </span>
                        </div>
                        <?php } ?>
                        <?php if ((float) ($inv['customs_clearance_amount'] ?? 0) > 0) { ?>
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('customs_clearance_costs'); ?></span>
                            <span class="rateb-kv-value rateb-ltr-num">
                                <?php echo number_format((float) $inv['customs_clearance_amount'], 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?>
                            </span>
                        </div>
                        <?php } ?>
                        <?php if ((float) ($inv['shipping_amount'] ?? 0) > 0) { ?>
                        <div class="rateb-kv-item">
                            <span class="rateb-kv-label"><?php echo __('shipping'); ?></span>
                            <span class="rateb-kv-value rateb-ltr-num">
                                <?php echo number_format((float) $inv['shipping_amount'], 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?>
                            </span>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php } ?>

            <div class="rateb-card rateb-po-lines-card mb-3">
                <div class="rateb-card-header">
                    <i class="fas fa-list me-1"></i> <?php echo __('line_items'); ?>
                </div>
                <?php Rateb\App\Core\View::partial('procurement-items-table', [
                    'items' => $items,
                    'showDeliveryCols' => true,
                    'order' => $order,
                    'hideFooter' => true,
                ]); ?>
            </div>

            <?php if ($canReceive) { ?>
            <div class="rateb-card rateb-po-grn-card">
                <div class="rateb-card-header">
                    <i class="fas fa-truck-loading me-1"></i> <?php echo __('receive_goods'); ?>
                </div>
                <div class="rateb-card-body">
                    <form method="post" action="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($order['id'] ?? 0) . '/receive'); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label" for="po_receive_warehouse"><?php echo __('warehouses'); ?></label>
                                <select name="warehouse_id" id="po_receive_warehouse" class="form-select rateb-form-control">
                                    <option value="">—</option>
                                    <?php foreach ($warehouses ?? [] as $wh) { ?>
                                    <option value="<?php echo (int) $wh['id']; ?>"<?php echo (int) ($order['warehouse_id'] ?? 0) === (int) $wh['id'] ? ' selected' : ''; ?>>
                                        <?php echo Rateb\App\Core\View::escape($wh['name'] ?? ''); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table rateb-table mb-0">
                                <thead>
                                <tr>
                                    <th><?php echo __('item_name'); ?></th>
                                    <th><?php echo __('quantity'); ?></th>
                                    <th><?php echo __('delivered'); ?></th>
                                    <th><?php echo __('receive_now'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $line) {
                                    $remaining = max(0, (float) ($line['quantity'] ?? 0) - (float) ($line['delivered_qty'] ?? 0));
                                    if ($remaining <= 0) {
                                        continue;
                                    }
                                    ?>
                                <tr>
                                    <td><?php echo Rateb\App\Core\View::escape($line['item_name'] ?? ''); ?></td>
                                    <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($line['quantity'] ?? 0); ?></td>
                                    <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($line['delivered_qty'] ?? 0); ?></td>
                                    <td>
                                        <input type="number" step="0.001" min="0" max="<?php echo $remaining; ?>"
                                            name="receive_qty[<?php echo (int) ($line['id'] ?? 0); ?>]"
                                            class="form-control form-control-sm rateb-form-control"
                                            value="<?php echo $remaining; ?>">
                                    </td>
                                </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-check"></i> <?php echo __('receive_goods'); ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="col-xl-4">
            <?php Rateb\App\Core\View::partial('procurement-show-summary', ['order' => $order]); ?>

            <?php if (!empty($docBarcode)) {
                Rateb\App\Core\View::partial('document-barcode-label', [
                    'docBarcode' => $docBarcode,
                    'compact' => true,
                ]);
            } ?>
        </div>
    </div>
</div>
