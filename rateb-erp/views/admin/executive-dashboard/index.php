<?php $d = $data ?? []; $platform = $d['platform'] ?? []; ?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('executive_dashboard')); ?></div>
    <div class="rateb-card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int) ($platform['total_companies'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('total_companies'); ?></div></div></div>
            <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo (int) ($platform['active_companies'] ?? 0); ?></div><div class="rateb-widget-label"><?php echo __('active_companies'); ?></div></div></div>
            <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo number_format((float) ($platform['revenue'] ?? 0), 2); ?></div><div class="rateb-widget-label"><?php echo __('revenue'); ?></div></div></div>
            <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo number_format((float) ($platform['inventory_value'] ?? 0), 2); ?></div><div class="rateb-widget-label"><?php echo __('inventory_value'); ?></div></div></div>
        </div>
        <h6><?php echo __('top_companies_by_po'); ?></h6>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $d['top_companies_po'] ?? [],
            'columns' => [
                ['name' => 'company_name', 'label' => 'companies'],
                ['name' => 'po_count', 'label' => 'purchase_orders'],
                ['name' => 'total', 'label' => 'total'],
            ],
        ]); ?>
    </div>
</div>
<?php if (!empty($expiring_contracts) || !empty($expiring_inventory)) { ?>
<div class="row g-3">
    <?php if (!empty($expiring_contracts)) { ?>
    <div class="col-md-6">
        <div class="rateb-card">
            <div class="rateb-card-header text-warning"><?php echo __('contract_expiry_alerts'); ?></div>
            <div class="rateb-card-body">
                <?php Rateb\App\Core\View::partial('workflow-list', [
                    'items' => $expiring_contracts,
                    'columns' => [
                        ['name' => 'contract_no', 'label' => 'contract_no'],
                        ['name' => 'end_date', 'label' => 'end_date'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
    <?php } ?>
    <?php if (!empty($expiring_inventory)) { ?>
    <div class="col-md-6">
        <div class="rateb-card">
            <div class="rateb-card-header text-warning"><?php echo __('expiry_alerts'); ?></div>
            <div class="rateb-card-body">
                <?php Rateb\App\Core\View::partial('workflow-list', [
                    'items' => $expiring_inventory,
                    'columns' => [
                        ['name' => 'item_name', 'label' => 'item_name'],
                        ['name' => 'expiry_date', 'label' => 'expiry_date'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php } ?>
