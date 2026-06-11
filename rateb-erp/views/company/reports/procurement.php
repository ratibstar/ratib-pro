<?php $d = $data ?? []; ?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <?php Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => $exportRoute ?? '']); ?>
    </div>
    <div class="rateb-card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int) ($d['purchase_requests'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('purchase_requests'); ?></div></div></div>
            <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int) ($d['purchase_orders'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('purchase_orders'); ?></div></div></div>
            <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo number_format((float) ($d['total_po_value'] ?? 0), 2); ?></div><div class="rateb-widget-label"><?php echo __('total_po_value'); ?></div></div></div>
        </div>
        <h6><?php echo __('po_monthly_trend'); ?></h6>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $d['po_monthly'] ?? [],
            'columns' => [
                ['name' => 'month', 'label' => 'month'],
                ['name' => 'c', 'label' => 'count'],
                ['name' => 'total', 'label' => 'total'],
            ],
        ]); ?>
    </div>
</div>
