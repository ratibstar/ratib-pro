<?php
$m = $metrics ?? [];
$limits = $limits ?? [];
$userCount = (int) ($userCount ?? 0);
$mods = $limits['modules'] ?? [];

$metrics = [];
foreach (
    [
        ['purchase_requests', 'blue'],
        ['purchase_orders', 'purple'],
        ['suppliers', 'teal'],
    ] as [$key, $tone]
) {
    $metrics[] = ['label' => __($key), 'value' => (int) ($m[$key] ?? 0), 'tone' => $tone];
}
$metrics[] = ['label' => __('users'), 'value' => $userCount, 'tone' => 'green'];

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

$footer = [
    ['label' => __('current_plan'), 'value' => Rateb\App\Core\View::escape($limits['plan_name'] ?? '—')],
    ['label' => __('user_limit'), 'value' => $userCount . ' / ' . (int) ($limits['user_limit'] ?? 0)],
    ['label' => __('storage_limit_mb'), 'value' => (int) ($limits['storage_limit_mb'] ?? 0) . ' MB'],
];
if ($mods !== []) {
    $footer[] = ['label' => __('plan_modules'), 'value' => implode(' · ', array_map(static fn ($mod) => __($mod), $mods))];
}

$alerts = [];
if (!empty($expiringInventory)) {
    foreach (array_slice($expiringInventory, 0, 4) as $item) {
        $alerts[] = [
            'url' => rateb_app_url('inventory'),
            'message' => (string) ($item['item_name'] ?? '') . ' · ' . (string) ($item['expiry_date'] ?? ''),
        ];
    }
}
if (!empty($expiringContracts)) {
    foreach (array_slice($expiringContracts, 0, 3) as $item) {
        $alerts[] = [
            'url' => rateb_app_url('contracts'),
            'message' => (string) ($item['contract_no'] ?? $item['title'] ?? '') . ' · ' . (string) ($item['end_date'] ?? ''),
        ];
    }
}

Rateb\App\Core\View::partial('dashboard/head');
?>
<div class="cm cm--solo" data-cm-dash="v5">
    <?php
    Rateb\App\Core\View::partial('dashboard/hero', [
        'tag' => __('approval_category_operations'),
        'title' => __('dashboard'),
        'subtitle' => __('company_dashboard_intro'),
        'actions' => $actions,
    ]);
    Rateb\App\Core\View::partial('dashboard/alerts', ['alerts' => $alerts]);
    ?>

    <div class="cm-split">
        <?php Rateb\App\Core\View::partial('dashboard/metrics-rail', [
            'metrics' => $metrics,
            'footer' => $footer,
        ]); ?>
        <main class="cm-work" aria-hidden="true"></main>
    </div>
</div>
