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

$maxCustomer = max(1.0, ...array_map(static fn ($r) => (float) ($r['total'] ?? 0), $topCustomers ?: [['total' => 1]]));
$maxItem = max(1.0, ...array_map(static fn ($r) => (float) ($r['total'] ?? 0), $topItems ?: [['total' => 1]]));

$kpiCards = [
    ['revenue_ytd', 'tone-green', 'fa-coins', true, 'revenue_ytd'],
    ['total_expenses', 'tone-red', 'fa-money-bill-transfer', true, 'expenses_ytd'],
    ['net_profit_ytd', 'tone-blue', 'fa-chart-line', true, 'net_profit_ytd'],
    ['inventory_value', 'tone-purple', 'fa-boxes-stacked', true, 'inventory_value'],
    ['new_customers', 'tone-teal', 'fa-user-plus', false, 'new_customers'],
    ['unpaid_invoices', 'tone-orange', 'fa-file-invoice-dollar', false, 'unpaid_invoices'],
];

$trendClass = static function (string $t): string {
    if ($t === '') {
        return 'neutral';
    }
    return str_starts_with($t, '-') ? 'down' : 'up';
};
?>
<link href="<?php echo rateb_asset('css/dashboard-modern.css'); ?>" rel="stylesheet">

<?php if (!$isAdmin && $companyId < 1 && rateb_is_super_admin()) { ?>
<div class="alert alert-warning mb-3 py-2 small"><?php echo __('accounting_select_company_hint'); ?></div>
<?php } ?>

<div class="rateb-dash rateb-dash--accounting">
    <header class="rateb-dash-hero">
        <div>
            <h1 class="rateb-dash-hero-title"><?php echo __('accounting_dashboard'); ?></h1>
            <p class="rateb-dash-hero-sub"><?php echo __('accounting_dashboard_intro'); ?></p>
        </div>
        <nav class="rateb-dash-hero-actions">
            <?php if ($canPost) { ?>
            <form method="post" action="<?php echo $isAdmin ? rateb_url('admin/accounting/sync') : rateb_app_url('accounting/sync'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-link btn-sm p-0 border-0"><?php echo __('accounting_sync'); ?></button>
            </form>
            <?php } ?>
            <?php if (!$isAdmin && $companyId > 0) { ?>
            <a href="<?php echo rateb_app_url('accounting/reports'); ?>"><?php echo __('accounting_reports'); ?></a>
            <a href="<?php echo rateb_app_url('accounting/cfo-dashboard'); ?>"><?php echo __('cfo_dashboard'); ?></a>
            <?php } ?>
        </nav>
    </header>

    <div class="rateb-dash-kpi-cards">
        <?php foreach ($kpiCards as [$key, $tone, $icon, $money, $trendKey]) {
            if (!$isAdmin && $companyId < 1 && in_array($key, ['revenue_ytd', 'net_profit_ytd', 'total_expenses'], true)) {
                continue;
            }
            $val = $m[$key] ?? 0;
            $display = $money ? number_format((float) $val, 2) . ' <small>SAR</small>' : (string) (int) $val;
            $trend = (string) ($trends[$trendKey] ?? $trends[$key] ?? '');
            ?>
        <div class="rateb-dash-kpi-card">
            <div class="rateb-dash-kpi-card-icon <?php echo Rateb\App\Core\View::escape($tone); ?>"><i class="fas <?php echo Rateb\App\Core\View::escape($icon); ?>"></i></div>
            <div class="rateb-dash-kpi-card-value"><?php echo $display; ?></div>
            <div class="rateb-dash-kpi-card-label"><?php echo __($key === 'total_expenses' ? 'total_expenses' : $key); ?></div>
            <?php if ($trend !== '') { ?>
            <div class="rateb-dash-kpi-card-trend <?php echo $trendClass($trend); ?>"><?php echo Rateb\App\Core\View::escape($trend); ?> <?php echo __('trend_from_last_month'); ?></div>
            <?php } ?>
        </div>
        <?php } ?>
    </div>

    <div class="rateb-dash-mid-row">
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('quick_shortcuts'); ?></div>
            <div class="rateb-dash-shortcuts-row">
                <?php if ($isAdmin) { ?>
                <a href="<?php echo rateb_url('admin/invoices/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-file-invoice"></i></span><?php echo __('new_invoice'); ?></a>
                <a href="<?php echo rateb_url('admin/payments/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-money-bill"></i></span><?php echo __('new_payment'); ?></a>
                <a href="<?php echo rateb_url('admin/chart-of-accounts'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-list"></i></span><?php echo __('chart_of_accounts'); ?></a>
                <a href="<?php echo rateb_url('admin/journal-entries'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-book"></i></span><?php echo __('journal_entries'); ?></a>
                <?php } else { ?>
                <a href="<?php echo rateb_app_url('journal-entries/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-file-invoice"></i></span><?php echo __('new_invoice'); ?></a>
                <a href="<?php echo rateb_app_url('accounting/supplier-payments/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-cart-shopping"></i></span><?php echo __('purchase_orders'); ?></a>
                <a href="<?php echo rateb_app_url('customers/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-user-plus"></i></span><?php echo __('add_customer'); ?></a>
                <a href="<?php echo rateb_app_url('accounting/supplier-payments/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-truck"></i></span><?php echo __('supplier_payment'); ?></a>
                <a href="<?php echo rateb_app_url('cash-vouchers/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-file-contract"></i></span><?php echo __('new_cash_voucher'); ?></a>
                <a href="<?php echo rateb_app_url('journal-entries/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-user-tie"></i></span><?php echo __('new_journal_entry'); ?></a>
                <?php } ?>
            </div>
        </section>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('smart_alerts'); ?></div>
            <div class="rateb-dash-panel-body flush">
                <?php if ($alerts === []) { ?>
                <p class="rateb-dash-feed-empty"><?php echo __('accounting_no_alerts'); ?></p>
                <?php } else { foreach ($alerts as $alert) { ?>
                <a href="<?php echo Rateb\App\Core\View::escape((string) ($alert['url'] ?? '#')); ?>" class="rateb-dash-alert-item">
                    <span class="rateb-dash-alert-icon"><i class="fas <?php echo Rateb\App\Core\View::escape((string) ($alert['icon'] ?? 'fa-bell')); ?>"></i></span>
                    <span class="rateb-dash-alert-text"><?php echo Rateb\App\Core\View::escape((string) ($alert['message'] ?? '')); ?></span>
                    <?php if (!empty($alert['count'])) { ?>
                    <span class="rateb-dash-alert-count"><?php echo (int) $alert['count']; ?></span>
                    <?php } ?>
                </a>
                <?php } } ?>
            </div>
        </section>
    </div>

    <div class="rateb-dash-charts-row">
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('revenue_vs_expenses'); ?></div>
            <div class="rateb-dash-chart-wrap" style="min-height:260px">
                <canvas id="chart-revenue-expenses"
                    data-labels='<?php echo Rateb\App\Core\View::escape($revExpLabels); ?>'
                    data-revenue='<?php echo Rateb\App\Core\View::escape($revExpRevenue); ?>'
                    data-expenses='<?php echo Rateb\App\Core\View::escape($revExpExpenses); ?>'
                    data-label-revenue="<?php echo Rateb\App\Core\View::escape(__('revenue')); ?>"
                    data-label-expenses="<?php echo Rateb\App\Core\View::escape(__('total_expenses')); ?>"></canvas>
            </div>
        </section>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('expense_breakdown'); ?></div>
            <div class="rateb-dash-chart-wrap" style="min-height:260px">
                <?php if ($breakdown === []) { ?>
                <p class="rateb-dash-feed-empty"><?php echo __('no_records'); ?></p>
                <?php } else { ?>
                <canvas id="chart-expense-breakdown" data-labels='<?php echo Rateb\App\Core\View::escape($bdLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($bdValues); ?>'></canvas>
                <?php } ?>
            </div>
        </section>
    </div>

    <div class="rateb-dash-two-col">
        <?php if ($topCustomers !== []) { ?>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('top_customers'); ?></div>
            <ol class="rateb-dash-rank-list">
                <?php foreach ($topCustomers as $row) {
                    $pct = min(100, ((float) ($row['total'] ?? 0) / $maxCustomer) * 100);
                    ?>
                <li class="rateb-dash-rank-item">
                    <div class="rateb-dash-rank-head">
                        <span class="rateb-dash-rank-name"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></span>
                        <span class="rateb-dash-rank-val"><?php echo number_format((float) ($row['total'] ?? 0), 0); ?></span>
                    </div>
                    <div class="rateb-dash-rank-bar"><div class="rateb-dash-rank-fill" style="width:<?php echo $pct; ?>%"></div></div>
                </li>
                <?php } ?>
            </ol>
        </section>
        <?php } ?>
        <?php if ($topItems !== []) { ?>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('top_sold_items'); ?></div>
            <ol class="rateb-dash-rank-list">
                <?php foreach ($topItems as $row) {
                    $pct = min(100, ((float) ($row['total'] ?? 0) / $maxItem) * 100);
                    ?>
                <li class="rateb-dash-rank-item">
                    <div class="rateb-dash-rank-head">
                        <span class="rateb-dash-rank-name"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></span>
                        <span class="rateb-dash-rank-val"><?php echo number_format((float) ($row['total'] ?? 0), 0); ?></span>
                    </div>
                    <div class="rateb-dash-rank-bar"><div class="rateb-dash-rank-fill" style="width:<?php echo $pct; ?>%"></div></div>
                </li>
                <?php } ?>
            </ol>
        </section>
        <?php } ?>
    </div>

    <?php if ($recent !== []) { ?>
    <section class="rateb-dash-panel mt-4">
        <div class="rateb-dash-panel-head"><?php echo __('recent_accounting_activity'); ?></div>
        <ul class="rateb-dash-timeline">
            <?php foreach ($recent as $row) {
                $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : ($row['description'] ?? '');
                ?>
            <li>
                <strong><?php echo Rateb\App\Core\View::escape((string) ($row['entry_no'] ?? '')); ?></strong>
                — <?php echo Rateb\App\Core\View::escape((string) $desc); ?>
                <time><?php echo Rateb\App\Core\View::escape(substr((string) ($row['entry_date'] ?? $row['issued_at'] ?? ''), 0, 16)); ?></time>
            </li>
            <?php } ?>
        </ul>
    </section>
    <?php } ?>

    <section class="rateb-dash-panel mt-4">
        <div class="rateb-dash-panel-head d-flex justify-content-between align-items-center">
            <span><?php echo __('trial_balance'); ?></span>
            <?php if (!$isAdmin && rateb_can_export_entity('accounting')) { ?>
            <a href="<?php echo rateb_app_url('accounting/export/trial-balance'); ?>" class="small text-decoration-none"><?php echo __('export_trial_balance'); ?></a>
            <?php } ?>
        </div>
        <div class="rateb-dash-panel-body flush">
            <table class="rateb-dash-table">
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
        <p class="rateb-dash-note"><?php echo $isAdmin ? __('accounting_sync_help') : __('accounting_auto_post_help'); ?></p>
    </section>
</div>
