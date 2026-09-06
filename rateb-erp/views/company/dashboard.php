<?php
$m = $metrics ?? ($dash['metrics'] ?? []);
$c = $charts ?? ($dash['charts'] ?? []);
$userCount = (int) ($userCount ?? 0);
$companyName = trim((string) ($companyName ?? ($dash['company_name'] ?? '')));
$recentActivity = $recentActivity ?? ($dash['recent_activity'] ?? []);

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$trendLabels = $c['procurement_trend']['labels'] ?? [];
if (!is_array($trendLabels) || $trendLabels === []) {
    $trendLabels = [];
    for ($i = 5; $i >= 0; $i--) {
        $trendLabels[] = date('Y-m', strtotime('-' . $i . ' months'));
    }
}
$prSeries = array_map('intval', $c['procurement_trend']['purchase_requests'] ?? []);
$poSeries = array_map('intval', $c['procurement_trend']['purchase_orders'] ?? []);
while (count($prSeries) < count($trendLabels)) {
    $prSeries[] = 0;
}
while (count($poSeries) < count($trendLabels)) {
    $poSeries[] = 0;
}
$healthRows = $c['inventory_health'] ?? [];
if (!is_array($healthRows) || $healthRows === []) {
    $healthRows = [
        ['label' => __('inventory_health_ok'), 'value' => 0],
        ['label' => __('inventory_health_low'), 'value' => 0],
        ['label' => __('inventory_health_out'), 'value' => 0],
        ['label' => __('inventory_health_expired'), 'value' => 0],
    ];
}
$prLabels = json_encode($trendLabels, $jsonFlags);
$prValues = json_encode($prSeries, $jsonFlags);
$poValues = json_encode($poSeries, $jsonFlags);
$invHealthLabels = json_encode(array_map(static fn ($r) => (string) ($r['label'] ?? ''), $healthRows), $jsonFlags);
$invHealthValues = json_encode(array_map('intval', array_column($healthRows, 'value')), $jsonFlags);

$metrics = [];
foreach (
    [
        ['purchase_requests', 'blue', (int) ($m['purchase_requests'] ?? 0)],
        ['purchase_orders', 'purple', (int) ($m['purchase_orders'] ?? 0)],
        ['inventory_items', 'teal', (int) ($m['inventory_items'] ?? 0)],
        ['suppliers', 'orange', (int) ($m['suppliers'] ?? 0)],
        ['employees_count', 'green', (int) ($m['employees'] ?? 0)],
        ['branches', 'blue', (int) ($m['branches'] ?? 0)],
    ] as [$key, $tone, $val]
) {
    $metrics[] = ['label' => __($key), 'value' => $val, 'tone' => $tone];
}
$metrics[] = [
    'label' => __('inventory_value'),
    'value' => Rateb\App\Core\View::escape((string) ($m['inventory_value_fmt'] ?? '0')),
    'tone' => 'purple',
];
$metrics[] = ['label' => __('users'), 'value' => $userCount, 'tone' => 'green'];

$actions = [
    ['href' => rateb_app_url('purchase-requests/create'), 'label' => __('purchase_requests'), 'icon' => 'fa-plus'],
    ['href' => rateb_app_url('inventory'), 'label' => __('inventory'), 'icon' => 'fa-boxes-stacked'],
    ['href' => rateb_app_url('suppliers'), 'label' => __('suppliers'), 'icon' => 'fa-truck-field'],
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
if ((int) ($m['pending_purchase_requests'] ?? 0) > 0) {
    $alerts[] = [
        'url' => rateb_app_url('purchase-requests'),
        'message' => __('company_alert_pending_pr', ['count' => (int) $m['pending_purchase_requests']]),
        'count' => (int) $m['pending_purchase_requests'],
    ];
}
if ((int) ($m['low_stock_items'] ?? 0) > 0) {
    $alerts[] = [
        'url' => rateb_app_url('inventory'),
        'message' => __('company_alert_low_stock', ['count' => (int) $m['low_stock_items']]),
        'count' => (int) $m['low_stock_items'],
    ];
}
if (!empty($expiringInventory)) {
    foreach (array_slice($expiringInventory, 0, 3) as $item) {
        $alerts[] = [
            'url' => rateb_app_url('inventory'),
            'message' => __('company_alert_expiring_inventory', [
                'item' => (string) ($item['item_name'] ?? ''),
                'date' => (string) ($item['expiry_date'] ?? ''),
            ]),
        ];
    }
}
if (!empty($expiringContracts)) {
    foreach (array_slice($expiringContracts, 0, 2) as $item) {
        $alerts[] = [
            'url' => rateb_app_url('contracts'),
            'message' => __('company_alert_expiring_contract', [
                'ref' => (string) ($item['contract_no'] ?? $item['title'] ?? ''),
                'date' => (string) ($item['end_date'] ?? ''),
            ]),
        ];
    }
}

$subtitle = $companyName !== ''
    ? __('company_dashboard_welcome', ['name' => $companyName])
    : __('company_dashboard_intro');

Rateb\App\Core\View::partial('dashboard/head');
?>
<!-- rateb-company-dashboard-v2 -->
<div class="cm cm--wide" data-cm-dash="v5c"
     data-rateb-chartjs="<?php echo Rateb\App\Core\View::escape(rateb_chartjs('4.4.3')); ?>"
     data-rateb-charts="<?php echo Rateb\App\Core\View::escape(rateb_asset('js/charts.js')); ?>">
    <?php
    Rateb\App\Core\View::partial('dashboard/hero', [
        'tag' => __('approval_category_operations'),
        'title' => __('dashboard'),
        'subtitle' => $subtitle . ' · ' . date('Y-m-d'),
        'actions' => $actions,
    ]);
    Rateb\App\Core\View::partial('dashboard/alerts', ['alerts' => $alerts]);
    Rateb\App\Core\View::partial('dashboard/metrics-strip', ['metrics' => $metrics]);
    ?>

    <div class="cm-body">
        <div class="cm-viz-grid cm-viz-grid--2">
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('company_procurement_trend'); ?></h2></header>
                <div class="cm-chart cm-chart--xl is-loading" data-chart-slot>
                    <canvas id="chart-revenue-expenses"
                        data-labels='<?php echo Rateb\App\Core\View::escape($prLabels); ?>'
                        data-revenue='<?php echo Rateb\App\Core\View::escape($poValues); ?>'
                        data-expenses='<?php echo Rateb\App\Core\View::escape($prValues); ?>'
                        data-label-revenue="<?php echo Rateb\App\Core\View::escape(__('purchase_orders')); ?>"
                        data-label-expenses="<?php echo Rateb\App\Core\View::escape(__('purchase_requests')); ?>"></canvas>
                </div>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('company_inventory_health'); ?></h2></header>
                <div class="cm-chart cm-chart--xl is-loading" data-chart-slot>
                    <canvas id="chart-expense-breakdown"
                        data-labels='<?php echo Rateb\App\Core\View::escape($invHealthLabels); ?>'
                        data-values='<?php echo Rateb\App\Core\View::escape($invHealthValues); ?>'></canvas>
                </div>
            </section>
        </div>

        <div class="cm-viz-grid cm-viz-grid--2">
            <section class="cm-board cm-board--fill cm-board--hint">
                <div class="cm-board__head"><?php echo __('quick_shortcuts'); ?></div>
                <div class="cm-hints">
                    <?php foreach ($actions as $act) { if (!empty($act['form'])) { continue; } ?>
                    <a class="cm-hint" href="<?php echo Rateb\App\Core\View::escape((string) $act['href']); ?>">
                        <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($act['icon'] ?? 'fa-link')); ?>"></i>
                        <span><?php echo Rateb\App\Core\View::escape((string) ($act['label'] ?? '')); ?></span>
                    </a>
                    <?php } ?>
                </div>
            </section>

            <section class="cm-board cm-board--fill">
                <div class="cm-board__head"><?php echo __('company_recent_activity'); ?></div>
                <?php if ($recentActivity === []) { ?>
                <p class="cm-empty"><?php echo __('company_no_recent_activity'); ?></p>
                <?php } else { ?>
                <div class="cm-activity">
                    <?php foreach ($recentActivity as $row) {
                        $kind = (string) ($row['kind'] ?? '');
                        $href = $kind === 'purchase_order'
                            ? rateb_app_url('purchase-orders')
                            : rateb_app_url('purchase-requests');
                        $icon = $kind === 'purchase_order' ? 'fa-file-invoice' : 'fa-file-circle-plus';
                        ?>
                    <a class="cm-activity__row" href="<?php echo Rateb\App\Core\View::escape($href); ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                        <span class="cm-activity__main">
                            <strong><?php echo Rateb\App\Core\View::escape((string) ($row['ref'] ?? '')); ?></strong>
                            <?php echo Rateb\App\Core\View::escape((string) ($row['title'] ?? '')); ?>
                        </span>
                        <span class="cm-activity__meta">
                            <em><?php echo Rateb\App\Core\View::escape(__((string) ($row['status'] ?? ''))); ?></em>
                            · <?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 10)); ?>
                        </span>
                    </a>
                    <?php } ?>
                </div>
                <?php } ?>
            </section>
        </div>
    </div>
</div>
