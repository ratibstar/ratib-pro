<?php
$m = $metrics ?? [];
$limits = $limits ?? [];
$userCount = (int) ($userCount ?? 0);
$mods = $limits['modules'] ?? [];

$kpis = [];
foreach (
    [
        ['purchase_requests', 'blue'],
        ['purchase_orders', 'purple'],
        ['suppliers', 'teal'],
    ] as [$key, $tone]
) {
    $kpis[] = ['label' => __($key), 'value' => (int) ($m[$key] ?? 0), 'tone' => $tone];
}
$kpis[] = ['label' => __('users'), 'value' => $userCount, 'tone' => 'green'];

$actions = [
    ['href' => rateb_app_url('purchase-requests/create'), 'label' => __('purchase_requests'), 'icon' => 'fa-plus'],
    ['href' => rateb_app_url('inventory'), 'label' => __('inventory'), 'icon' => 'fa-boxes-stacked'],
];
if (rateb_nav_can('accounting.view', 'accounting')) {
    array_unshift($actions, [
        'href' => rateb_app_url('accounting'),
        'label' => __('accounting_dashboard'),
        'icon' => 'fa-calculator',
        'primary' => true,
    ]);
}

$alerts = [];
if (!empty($expiringInventory)) {
    foreach (array_slice($expiringInventory, 0, 4) as $item) {
        $alerts[] = [
            'url' => rateb_app_url('inventory'),
            'icon' => 'fa-box-open',
            'message' => (string) ($item['item_name'] ?? '') . ' · ' . (string) ($item['expiry_date'] ?? ''),
        ];
    }
}
if (!empty($expiringContracts)) {
    foreach (array_slice($expiringContracts, 0, 3) as $item) {
        $alerts[] = [
            'url' => rateb_app_url('contracts'),
            'icon' => 'fa-file-contract',
            'message' => (string) ($item['contract_no'] ?? $item['title'] ?? '') . ' · ' . (string) ($item['end_date'] ?? ''),
        ];
    }
}

Rateb\App\Core\View::partial('dashboard/head');
?>
<div class="rdx">
    <?php
    Rateb\App\Core\View::partial('dashboard/top', [
        'title' => __('dashboard'),
        'subtitle' => __('company_dashboard_intro'),
        'actions' => $actions,
    ]);
    ?>

    <dl class="rdx-meta">
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
        <div>
            <dt><?php echo __('plan_modules'); ?></dt>
            <dd class="rdx-chips">
                <?php foreach ($mods as $mod) { ?>
                <span class="rdx-chip"><?php echo Rateb\App\Core\View::escape(__($mod)); ?></span>
                <?php } ?>
            </dd>
        </div>
        <?php } ?>
    </dl>

    <?php Rateb\App\Core\View::partial('dashboard/kpis', ['items' => $kpis, 'cols' => '4']); ?>

    <?php if ($alerts !== []) { ?>
    <div class="rdx-layout rdx-layout--single">
        <?php Rateb\App\Core\View::partial('dashboard/alerts', [
            'alerts' => $alerts,
            'empty' => __('dashboard_no_alerts'),
        ]); ?>
    </div>
    <?php } ?>
</div>
