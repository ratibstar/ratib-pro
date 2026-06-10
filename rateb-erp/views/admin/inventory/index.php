<div class="rateb-card mb-3">
    <div class="rateb-card-body d-flex justify-content-between align-items-center">
        <span><?php echo __('inventory_value'); ?>: <strong><?php echo number_format((float)($total_value ?? 0), 2); ?></strong></span>
    </div>
</div>
<?php Rateb\App\Core\View::partial('crud-index', ['title' => __('inventory'), 'items' => $items ?? [], 'csrf' => $csrf, 'routePrefix' => 'admin/inventory']); ?>
