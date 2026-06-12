<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('purchase_orders'); ?> #<?php echo Rateb\App\Core\View::escape($order['order_no'] ?? ''); ?></div>
    <div class="rateb-card-body">
        <dl class="row">
            <?php foreach ($order ?? [] as $k => $v) { ?>
            <dt class="col-sm-3"><?php echo Rateb\App\Core\View::escape($k); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($v); ?></dd>
            <?php } ?>
        </dl>
        <h6 class="mt-4"><?php echo __('purchase_orders'); ?> Items</h6>
        <?php Rateb\App\Core\View::partial('crud-index', [
            'title' => '',
            'items' => $items ?? [],
            'csrf' => $csrf,
            'routePrefix' => rateb_app_route('purchase-orders'),
            'bulkEnabled' => false,
            'createEnabled' => false,
            'actionsEnabled' => false,
        ]); ?>
    </div>
</div>
