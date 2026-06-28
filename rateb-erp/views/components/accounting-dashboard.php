<?php
/** @var array<string, mixed> $dash */
/** @var array<int, array<string, mixed>> $trial */
$dash = $dash ?? [];
$m = $dash['metrics'] ?? [];
$trends = $dash['trends'] ?? [];
$alerts = $dash['alerts'] ?? [];
$recent = $dash['recent'] ?? [];
$charts = $dash['charts'] ?? [];
$topCustomers = $dash['top_customers'] ?? [];
$topItems = $dash['top_items'] ?? [];
$breakdown = $dash['expense_breakdown'] ?? [];
$trial = $trial ?? [];
$isAdmin = (bool) ($isAdmin ?? false);
$canPost = (bool) ($canPost ?? false);
$companyId = (int) ($selectedCompanyId ?? 0);

$months = [];
foreach (array_merge($charts['monthly_revenue'] ?? [], $charts['monthly_expenses'] ?? []) as $row) {
    $months[(string) ($row['month'] ?? '')] = true;
}
$months = array_keys($months);
sort($months);
$revMap = [];
foreach ($charts['monthly_revenue'] ?? [] as $row) {
    $revMap[(string) $row['month']] = (float) $row['total'];
}
$expMap = [];
foreach ($charts['monthly_expenses'] ?? [] as $row) {
    $expMap[(string) $row['month']] = (float) $row['total'];
}
$revExpLabels = json_encode($months);
$revExpRevenue = json_encode(array_map(static fn ($mo) => $revMap[$mo] ?? 0, $months));
$revExpExpenses = json_encode(array_map(static fn ($mo) => $expMap[$mo] ?? 0, $months));
$bdLabels = json_encode(array_map(static fn ($r) => __((string) ($r['label'] ?? '')), $breakdown));
$bdValues = json_encode(array_map('floatval', array_column($breakdown, 'value')));

$trendDir = static function (string $t): string {
    if ($t === '') {
        return '';
    }
    return str_starts_with($t, '-') ? 'down' : 'up';
};

$kpiDefs = [
    ['revenue_ytd', 'green', true, 'revenue_ytd'],
    ['total_expenses', 'red', true, 'expenses_ytd'],
    ['net_profit_ytd', 'blue', true, 'net_profit_ytd'],
    ['inventory_value', 'purple', true, 'inventory_value'],
    ['new_customers', 'teal', false, 'new_customers'],
    ['unpaid_invoices', 'orange', false, 'unpaid_invoices'],
];
$kpis = [];
foreach ($kpiDefs as [$key, $tone, $money, $trendKey]) {
    if (!$isAdmin && $companyId < 1 && in_array($key, ['revenue_ytd', 'net_profit_ytd', 'total_expenses'], true)) {
        continue;
    }
    $val = $m[$key] ?? 0;
    $display = $money ? number_format((float) $val, 2) . ' <small>SAR</small>' : (string) (int) $val;
    $trend = (string) ($trends[$trendKey] ?? $trends[$key] ?? '');
    $kpis[] = [
        'label' => __($key === 'total_expenses' ? 'total_expenses' : $key),
        'value' => $display,
        'tone' => $tone,
        'trend' => $trend,
        'trendDir' => $trendDir($trend),
    ];
}

$actions = [];
if ($canPost) {
    $actions[] = [
        'form' => true,
        'href' => $isAdmin ? rateb_url('admin/accounting/sync') : rateb_app_url('accounting/sync'),
        'label' => __('accounting_sync'),
        'icon' => 'fa-rotate',
        'csrf' => $csrf ?? '',
        'ghost' => true,
    ];
}
if ($isAdmin) {
    $actions[] = ['href' => rateb_url('admin/invoices/create'), 'label' => __('new_invoice'), 'icon' => 'fa-file-invoice'];
    $actions[] = ['href' => rateb_url('admin/journal-entries'), 'label' => __('journal_entries'), 'icon' => 'fa-book'];
} else {
    if ($companyId > 0) {
        $actions[] = ['href' => rateb_app_url('accounting/reports'), 'label' => __('accounting_reports'), 'icon' => 'fa-chart-pie', 'ghost' => true];
        $actions[] = ['href' => rateb_app_url('accounting/cfo-dashboard'), 'label' => __('cfo_dashboard'), 'icon' => 'fa-chart-line', 'ghost' => true];
    }
    $actions[] = ['href' => rateb_app_url('journal-entries/create'), 'label' => __('new_journal_entry'), 'icon' => 'fa-plus', 'primary' => true];
}

$maxCustomer = max(1.0, ...array_map(static fn ($r) => (float) ($r['total'] ?? 0), $topCustomers ?: [['total' => 1]]));
$maxItem = max(1.0, ...array_map(static fn ($r) => (float) ($r['total'] ?? 0), $topItems ?: [['total' => 1]]));

Rateb\App\Core\View::partial('dashboard/head');
?>
<?php if (!$isAdmin && $companyId < 1 && rateb_is_super_admin()) { ?>
<div class="alert alert-warning mb-3 py-2 small"><?php echo __('accounting_select_company_hint'); ?></div>
<?php } ?>

<div class="rdx">
    <?php
    Rateb\App\Core\View::partial('dashboard/top', [
        'title' => __('accounting_dashboard'),
        'subtitle' => __('accounting_dashboard_intro'),
        'actions' => $actions,
    ]);
    Rateb\App\Core\View::partial('dashboard/kpis', ['items' => $kpis]);
    ?>

    <div class="rdx-layout">
        <div class="rdx-stack">
            <div class="rdx-row-charts">
                <div class="rdx-card">
                    <div class="rdx-card-head"><?php echo __('revenue_vs_expenses'); ?></div>
                    <div class="rdx-chart">
                        <canvas id="chart-revenue-expenses"
                            data-labels='<?php echo Rateb\App\Core\View::escape($revExpLabels); ?>'
                            data-revenue='<?php echo Rateb\App\Core\View::escape($revExpRevenue); ?>'
                            data-expenses='<?php echo Rateb\App\Core\View::escape($revExpExpenses); ?>'
                            data-label-revenue="<?php echo Rateb\App\Core\View::escape(__('revenue')); ?>"
                            data-label-expenses="<?php echo Rateb\App\Core\View::escape(__('total_expenses')); ?>"></canvas>
                    </div>
                </div>
                <div class="rdx-card">
                    <div class="rdx-card-head"><?php echo __('expense_breakdown'); ?></div>
                    <?php if ($breakdown === []) { ?>
                    <p class="rdx-empty"><?php echo __('no_records'); ?></p>
                    <?php } else { ?>
                    <div class="rdx-chart">
                        <canvas id="chart-expense-breakdown" data-labels='<?php echo Rateb\App\Core\View::escape($bdLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($bdValues); ?>'></canvas>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="rdx-row-2">
                <?php
                Rateb\App\Core\View::partial('dashboard/ranks', [
                    'title' => __('top_customers'),
                    'rows' => array_map(static fn ($r) => ['name' => (string) ($r['name'] ?? ''), 'total' => (float) ($r['total'] ?? 0)], $topCustomers),
                    'max' => $maxCustomer,
                ]);
                Rateb\App\Core\View::partial('dashboard/ranks', [
                    'title' => __('top_sold_items'),
                    'rows' => array_map(static fn ($r) => ['name' => (string) ($r['name'] ?? ''), 'total' => (float) ($r['total'] ?? 0)], $topItems),
                    'max' => $maxItem,
                ]);
                ?>
            </div>

            <?php if ($recent !== []) { ?>
            <div class="rdx-card">
                <div class="rdx-card-head"><?php echo __('recent_accounting_activity'); ?></div>
                <ul class="rdx-feed">
                    <?php foreach ($recent as $row) {
                        $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : ($row['description'] ?? '');
                        ?>
                    <li>
                        <span class="rdx-feed-dot"></span>
                        <div class="rdx-feed-body">
                            <strong><?php echo Rateb\App\Core\View::escape((string) ($row['entry_no'] ?? '')); ?></strong>
                            — <?php echo Rateb\App\Core\View::escape((string) $desc); ?>
                            <time><?php echo Rateb\App\Core\View::escape(substr((string) ($row['entry_date'] ?? $row['issued_at'] ?? ''), 0, 16)); ?></time>
                        </div>
                    </li>
                    <?php } ?>
                </ul>
            </div>
            <?php } ?>

            <div class="rdx-card">
                <div class="rdx-card-head">
                    <span><?php echo __('trial_balance'); ?></span>
                    <?php if (!$isAdmin && rateb_can_export_entity('accounting')) { ?>
                    <a href="<?php echo rateb_app_url('accounting/export/trial-balance'); ?>"><?php echo __('export_trial_balance'); ?></a>
                    <?php } ?>
                </div>
                <div class="rdx-card-body rdx-card-body--flush">
                    <table class="rdx-table">
                        <thead><tr><th><?php echo __('code'); ?></th><th><?php echo __('name'); ?></th><th class="text-end"><?php echo __('debit'); ?></th><th class="text-end"><?php echo __('credit'); ?></th></tr></thead>
                        <tbody>
                        <?php
                        $shown = 0;
                        if ($trial === []) {
                            echo '<tr><td colspan="4" class="text-center text-muted py-3">' . __('no_records') . '</td></tr>';
                        } else {
                            foreach ($trial as $row) {
                                if ($shown >= 10) {
                                    break;
                                }
                                $dr = (float) ($row['total_debit'] ?? 0);
                                $cr = (float) ($row['total_credit'] ?? 0);
                                if ($dr <= 0 && $cr <= 0) {
                                    continue;
                                }
                                $shown++;
                                $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : $row['name'];
                                echo '<tr><td>' . Rateb\App\Core\View::escape($row['code']) . '</td><td>' . Rateb\App\Core\View::escape($name) . '</td><td class="text-end">' . number_format($dr, 2) . '</td><td class="text-end">' . number_format($cr, 2) . '</td></tr>';
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
                <p class="rdx-footnote"><?php echo $isAdmin ? __('accounting_sync_help') : __('accounting_auto_post_help'); ?></p>
            </div>
        </div>

        <aside class="rdx-stack">
            <?php Rateb\App\Core\View::partial('dashboard/alerts', [
                'alerts' => $alerts,
                'empty' => __('accounting_no_alerts'),
            ]); ?>
        </aside>
    </div>
</div>
