<?php
$m = $metrics ?? [];
$limits = $limits ?? [];
$userCount = (int) ($userCount ?? 0);
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('plan_limits'); ?></div>
    <div class="rateb-card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small"><?php echo __('current_plan'); ?></div>
                <div class="fw-semibold"><?php echo Rateb\App\Core\View::escape($limits['plan_name'] ?? '—'); ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?php echo __('user_limit'); ?></div>
                <div class="fw-semibold"><?php echo $userCount; ?> / <?php echo (int) ($limits['user_limit'] ?? 0); ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?php echo __('storage_limit_mb'); ?></div>
                <div class="fw-semibold"><?php echo (int) ($limits['storage_limit_mb'] ?? 0); ?> MB</div>
            </div>
            <div class="col-12">
                <div class="text-muted small mb-1"><?php echo __('plan_modules'); ?></div>
                <div class="d-flex flex-wrap gap-2">
                    <?php
                    $mods = $limits['modules'] ?? [];
                    if ($mods === []) {
                        echo '<span class="text-muted">—</span>';
                    } else {
                        foreach ($mods as $mod) {
                            echo '<span class="badge bg-secondary">' . Rateb\App\Core\View::escape(__( $mod)) . '</span>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int)($m['purchase_requests'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('purchase_requests'); ?></div></div></div>
    <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int)($m['purchase_orders'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('purchase_orders'); ?></div></div></div>
    <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo number_format((float)($m['inventory_value'] ?? 0), 2); ?></div><div class="rateb-widget-label"><?php echo __('inventory_value'); ?></div></div></div>
    <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int)($m['suppliers'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('suppliers'); ?></div></div></div>
</div>
<?php if (!empty($expiringInventory) || !empty($expiringContracts)) { ?>
<div class="row g-3 mt-2">
    <?php if (!empty($expiringInventory)) { ?>
    <div class="col-md-6">
        <div class="rateb-card">
            <div class="rateb-card-header text-warning"><i class="fas fa-hourglass-half"></i> <?php echo __('expiry_alerts'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('workflow-list', [
                    'items' => array_slice($expiringInventory, 0, 5),
                    'columns' => [
                        ['name' => 'item_name', 'label' => 'item_name'],
                        ['name' => 'expiry_date', 'label' => 'expiry_date'],
                        ['name' => 'quantity', 'label' => 'quantity'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
    <?php } ?>
    <?php if (!empty($expiringContracts)) { ?>
    <div class="col-md-6">
        <div class="rateb-card">
            <div class="rateb-card-header text-warning"><i class="fas fa-file-contract"></i> <?php echo __('contract_expiry_alerts'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('workflow-list', [
                    'items' => array_slice($expiringContracts, 0, 5),
                    'columns' => [
                        ['name' => 'contract_no', 'label' => 'contract_no'],
                        ['name' => 'title', 'label' => 'title'],
                        ['name' => 'end_date', 'label' => 'end_date'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php } ?>
