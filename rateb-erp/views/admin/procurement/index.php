<div class="row g-3">
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('purchase_requests'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', ['title' => '', 'items' => $purchase_requests ?? [], 'csrf' => $csrf, 'routePrefix' => 'admin/procurement', 'bulkEnabled' => false, 'createEnabled' => false, 'actionsEnabled' => false]); ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('purchase_orders'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', ['title' => '', 'items' => $purchase_orders ?? [], 'csrf' => $csrf, 'routePrefix' => 'admin/procurement', 'bulkEnabled' => false, 'createEnabled' => false, 'actionsEnabled' => false]); ?>
            </div>
        </div>
    </div>
</div>
