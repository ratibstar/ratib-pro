<?php
/** @var array<string, mixed> $order */
$order = $order ?? [];
$currency = (string) ($order['currency'] ?? 'SAR');
$subtotal = (float) ($order['subtotal'] ?? 0);
$discount = (float) ($order['discount_amount'] ?? 0);
$shipping = (float) ($order['shipping_amount'] ?? 0);
$customs = (float) ($order['customs_clearance_amount'] ?? 0);
$tax = (float) ($order['tax_amount'] ?? 0);
$total = (float) ($order['total_amount'] ?? $order['total_estimated'] ?? 0);

$rows = [
    ['label' => __('subtotal'), 'value' => $subtotal, 'class' => ''],
];
if ($discount > 0) {
    $rows[] = ['label' => __('discount'), 'value' => -$discount, 'class' => 'rateb-po-summary-row--discount'];
}
if ($shipping > 0) {
    $rows[] = ['label' => __('shipping'), 'value' => $shipping, 'class' => ''];
}
if ($customs > 0) {
    $rows[] = ['label' => __('customs_clearance_costs'), 'value' => $customs, 'class' => ''];
}
$rows[] = ['label' => __('tax_amount'), 'value' => $tax, 'class' => ''];
?>
<div class="rateb-card rateb-po-summary-card mb-3">
    <div class="rateb-card-header">
        <i class="fas fa-calculator me-1"></i> <?php echo __('procurement_summary'); ?>
    </div>
    <div class="rateb-card-body">
        <div class="rateb-po-summary-rows">
            <?php foreach ($rows as $row) { ?>
            <div class="rateb-po-summary-row <?php echo Rateb\App\Core\View::escape($row['class']); ?>">
                <span><?php echo Rateb\App\Core\View::escape($row['label']); ?></span>
                <span><?php echo number_format((float) $row['value'], 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></span>
            </div>
            <?php } ?>
            <div class="rateb-po-summary-row rateb-po-summary-row--total">
                <span><?php echo __('total'); ?></span>
                <span><?php echo number_format($total, 2); ?> <?php echo Rateb\App\Core\View::escape($currency); ?></span>
            </div>
        </div>
    </div>
</div>
