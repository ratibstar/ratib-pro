<?php
$m = $metrics ?? [];
$limits = $limits ?? [];
$userCount = (int) ($userCount ?? 0);
$mods = $limits['modules'] ?? [];

$kpiCards = [
    ['purchase_requests', 'tone-blue', 'fa-file-lines'],
    ['purchase_orders', 'tone-purple', 'fa-cart-shopping'],
    ['suppliers', 'tone-teal', 'fa-truck'],
];
?>
<link href="<?php echo rateb_asset('css/dashboard-modern.css'); ?>" rel="stylesheet">

<div class="rateb-dash rateb-dash--company">
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

    <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
    <div class="rateb-dash-accounting-banner">
        <i class="fas fa-calculator text-primary"></i>
        <span><?php echo __('dashboard_accounting_moved_hint'); ?></span>
        <a href="<?php echo rateb_app_url('accounting'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('accounting_dashboard'); ?></a>
    </div>
    <?php } ?>

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

    <div class="rateb-dash-kpi-cards" style="grid-template-columns:repeat(4,1fr)">
        <?php foreach ($kpiCards as [$key, $tone, $icon]) { ?>
        <div class="rateb-dash-kpi-card">
            <div class="rateb-dash-kpi-card-icon <?php echo Rateb\App\Core\View::escape($tone); ?>"><i class="fas <?php echo Rateb\App\Core\View::escape($icon); ?>"></i></div>
            <div class="rateb-dash-kpi-card-value"><?php echo (int) ($m[$key] ?? 0); ?></div>
            <div class="rateb-dash-kpi-card-label"><?php echo __($key); ?></div>
        </div>
        <?php } ?>
        <div class="rateb-dash-kpi-card">
            <div class="rateb-dash-kpi-card-icon tone-green"><i class="fas fa-users"></i></div>
            <div class="rateb-dash-kpi-card-value"><?php echo $userCount; ?></div>
            <div class="rateb-dash-kpi-card-label"><?php echo __('users'); ?></div>
        </div>
    </div>

    <div class="rateb-dash-mid-row">
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('quick_shortcuts'); ?></div>
            <div class="rateb-dash-shortcuts-row">
                <a href="<?php echo rateb_app_url('purchase-requests/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-file-lines"></i></span><?php echo __('purchase_requests'); ?></a>
                <a href="<?php echo rateb_app_url('purchase-orders'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-cart-shopping"></i></span><?php echo __('purchase_orders'); ?></a>
                <a href="<?php echo rateb_app_url('inventory'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-boxes-stacked"></i></span><?php echo __('inventory'); ?></a>
                <a href="<?php echo rateb_app_url('suppliers'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-truck"></i></span><?php echo __('suppliers'); ?></a>
                <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
                <a href="<?php echo rateb_app_url('accounting'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-calculator"></i></span><?php echo __('accounting'); ?></a>
                <?php } ?>
            </div>
        </section>
        <?php if (!empty($expiringInventory) || !empty($expiringContracts)) { ?>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('smart_alerts'); ?></div>
            <div class="rateb-dash-panel-body flush">
                <?php if (!empty($expiringInventory)) { foreach (array_slice($expiringInventory, 0, 4) as $item) { ?>
                <a href="<?php echo rateb_app_url('inventory'); ?>" class="rateb-dash-alert-item">
                    <span class="rateb-dash-alert-icon"><i class="fas fa-box-open"></i></span>
                    <span class="rateb-dash-alert-text"><?php echo Rateb\App\Core\View::escape((string) ($item['item_name'] ?? '')); ?> · <?php echo Rateb\App\Core\View::escape((string) ($item['expiry_date'] ?? '')); ?></span>
                </a>
                <?php } } ?>
                <?php if (!empty($expiringContracts)) { foreach (array_slice($expiringContracts, 0, 3) as $item) { ?>
                <a href="<?php echo rateb_app_url('contracts'); ?>" class="rateb-dash-alert-item">
                    <span class="rateb-dash-alert-icon"><i class="fas fa-file-contract"></i></span>
                    <span class="rateb-dash-alert-text"><?php echo Rateb\App\Core\View::escape((string) ($item['contract_no'] ?? $item['title'] ?? '')); ?> · <?php echo Rateb\App\Core\View::escape((string) ($item['end_date'] ?? '')); ?></span>
                </a>
                <?php } } ?>
            </div>
        </section>
        <?php } ?>
    </div>
</div>
