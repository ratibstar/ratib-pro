<?php $m = $metrics ?? []; ?>
<div class="row g-3">
    <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int)($m['purchase_requests'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('purchase_requests'); ?></div></div></div>
    <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int)($m['purchase_orders'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('purchase_orders'); ?></div></div></div>
    <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo number_format((float)($m['inventory_value'] ?? 0), 2); ?></div><div class="rateb-widget-label"><?php echo __('inventory_value'); ?></div></div></div>
    <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int)($m['suppliers'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('suppliers'); ?></div></div></div>
</div>
