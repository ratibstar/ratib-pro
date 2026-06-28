<?php
$m = $metrics ?? [];
$limits = $limits ?? [];
$userCount = (int) ($userCount ?? 0);
$mods = $limits['modules'] ?? [];
?>
<link href="<?php echo rateb_asset('css/dashboard-modern.css'); ?>" rel="stylesheet">

<div class="rateb-dash">
    <header class="rateb-dash-hero">
        <div>
            <h1 class="rateb-dash-hero-title"><?php echo __('dashboard'); ?></h1>
            <p class="rateb-dash-hero-sub"><?php echo __('company_dashboard_intro'); ?></p>
        </div>
        <nav class="rateb-dash-hero-actions" aria-label="<?php echo __('quick_shortcuts'); ?>">
            <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
            <a href="<?php echo rateb_app_url('accounting'); ?>"><?php echo __('accounting_dashboard'); ?></a>
            <?php } ?>
            <a href="<?php echo rateb_app_url('purchase-requests'); ?>"><?php echo __('purchase_requests'); ?></a>
            <a href="<?php echo rateb_app_url('inventory'); ?>"><?php echo __('inventory'); ?></a>
        </nav>
    </header>

    <dl class="rateb-dash-plan-bar">
        <div>
            <dt><?php echo __('current_plan'); ?></dt>
            <dd><?php echo Rateb\App\Core\View::escape($limits['plan_name'] ?? '—'); ?></dd>
        </div>
        <div>
            <dt><?php echo __('user_limit'); ?></dt>
            <dd><?php echo $userCount; ?> / <?php echo (int) ($limits['user_limit'] ?? 0); ?></dd>
        </div>
        <div>
            <dt><?php echo __('storage_limit_mb'); ?></dt>
            <dd><?php echo (int) ($limits['storage_limit_mb'] ?? 0); ?> MB</dd>
        </div>
        <?php if ($mods !== []) { ?>
        <div style="flex:1;min-width:180px">
            <dt><?php echo __('plan_modules'); ?></dt>
            <dd class="rateb-dash-modules mt-1">
                <?php foreach ($mods as $mod) { ?>
                <span><?php echo Rateb\App\Core\View::escape(__($mod)); ?></span>
                <?php } ?>
            </dd>
        </div>
        <?php } ?>
    </dl>

    <div class="rateb-dash-kpi-row">
        <div class="rateb-dash-kpi">
            <div class="rateb-dash-kpi-value"><?php echo (int) ($m['purchase_requests'] ?? 0); ?></div>
            <div class="rateb-dash-kpi-label"><?php echo __('purchase_requests'); ?></div>
        </div>
        <div class="rateb-dash-kpi">
            <div class="rateb-dash-kpi-value"><?php echo (int) ($m['purchase_orders'] ?? 0); ?></div>
            <div class="rateb-dash-kpi-label"><?php echo __('purchase_orders'); ?></div>
        </div>
        <div class="rateb-dash-kpi">
            <div class="rateb-dash-kpi-value"><?php echo number_format((float) ($m['suppliers'] ?? 0), 0); ?></div>
            <div class="rateb-dash-kpi-label"><?php echo __('suppliers'); ?></div>
        </div>
        <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
        <a href="<?php echo rateb_app_url('accounting'); ?>" class="rateb-dash-kpi text-decoration-none" style="color:inherit">
            <div class="rateb-dash-kpi-value"><?php echo number_format((float) ($m['inventory_value'] ?? 0), 0); ?></div>
            <div class="rateb-dash-kpi-label"><?php echo __('inventory_value'); ?> → <?php echo __('accounting'); ?></div>
        </a>
        <?php } else { ?>
        <div class="rateb-dash-kpi">
            <div class="rateb-dash-kpi-value"><?php echo number_format((float) ($m['inventory_value'] ?? 0), 0); ?></div>
            <div class="rateb-dash-kpi-label"><?php echo __('inventory_value'); ?></div>
        </div>
        <?php } ?>
    </div>

    <?php if (!empty($expiringInventory) || !empty($expiringContracts)) { ?>
    <div class="rateb-dash-two-col">
        <?php if (!empty($expiringInventory)) { ?>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('expiry_alerts'); ?></div>
            <div class="rateb-dash-panel-body flush">
                <ul class="rateb-dash-feed">
                    <?php foreach (array_slice($expiringInventory, 0, 5) as $item) { ?>
                    <li class="rateb-dash-feed-item">
                        <?php echo Rateb\App\Core\View::escape((string) ($item['item_name'] ?? '')); ?>
                        <span class="text-muted"> · <?php echo Rateb\App\Core\View::escape((string) ($item['expiry_date'] ?? '')); ?></span>
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </section>
        <?php } ?>
        <?php if (!empty($expiringContracts)) { ?>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('contract_expiry_alerts'); ?></div>
            <div class="rateb-dash-panel-body flush">
                <ul class="rateb-dash-feed">
                    <?php foreach (array_slice($expiringContracts, 0, 5) as $item) { ?>
                    <li class="rateb-dash-feed-item">
                        <?php echo Rateb\App\Core\View::escape((string) ($item['contract_no'] ?? $item['title'] ?? '')); ?>
                        <span class="text-muted"> · <?php echo Rateb\App\Core\View::escape((string) ($item['end_date'] ?? '')); ?></span>
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </section>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
    <p class="rateb-dash-note mt-3" style="border:none;padding:0"><?php echo __('dashboard_accounting_moved_hint'); ?></p>
    <?php } ?>
</div>
