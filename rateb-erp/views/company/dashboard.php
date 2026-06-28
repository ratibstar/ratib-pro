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
<div class="cm cm--wide" data-cm-dash="v5c">
    <?php
    Rateb\App\Core\View::partial('dashboard/hero', [
        'tag' => __('approval_category_operations'),
        'title' => __('dashboard'),
        'subtitle' => __('company_dashboard_intro'),
        'actions' => $actions,
    ]);
    Rateb\App\Core\View::partial('dashboard/alerts', ['alerts' => $alerts]);
    Rateb\App\Core\View::partial('dashboard/metrics-strip', ['metrics' => $metrics]);
    ?>

    <div class="cm-body">
        <div class="cm-viz-grid cm-viz-grid--2">
            <section class="cm-board cm-board--fill">
                <div class="cm-board__head"><?php echo __('current_plan'); ?></div>
                <dl class="cm-meta">
                    <?php foreach ($footer as $row) { ?>
                    <div class="cm-meta__row">
                        <dt><?php echo Rateb\App\Core\View::escape((string) ($row['label'] ?? '')); ?></dt>
                        <dd><?php echo $row['value'] ?? ''; ?></dd>
                    </div>
                    <?php } ?>
                </dl>
            </section>
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
        </div>
    </div>
</div>
