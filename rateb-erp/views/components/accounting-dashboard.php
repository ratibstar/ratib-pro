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
$revOnlyLabels = json_encode(array_column($charts['monthly_revenue'] ?? [], 'month'));
$revOnlyValues = json_encode(array_map('floatval', array_column($charts['monthly_revenue'] ?? [], 'total')));
$expOnlyLabels = json_encode(array_column($charts['monthly_expenses'] ?? [], 'month'));
$expOnlyValues = json_encode(array_map('floatval', array_column($charts['monthly_expenses'] ?? [], 'total')));
$bdLabels = json_encode(array_map(static fn ($r) => __((string) ($r['label'] ?? '')), $breakdown));
$bdValues = json_encode(array_map('floatval', array_column($breakdown, 'value')));
$chartLbl = static function (string $key): string {
    $payKey = 'payment_status_' . $key;
    $pay = __($payKey);
    if ($pay !== $payKey) {
        return $pay;
    }
    $plain = __($key);
    return $plain !== $key ? $plain : $key;
};
$arApLabels = json_encode(array_map($chartLbl, array_column($charts['ar_ap'] ?? [], 'label')));
$arApValues = json_encode(array_map('floatval', array_column($charts['ar_ap'] ?? [], 'value')));
$journalLabels = json_encode(array_column($charts['journal_activity'] ?? [], 'month'));
$journalValues = json_encode(array_map('intval', array_column($charts['journal_activity'] ?? [], 'total')));

$trendDir = static function (string $t): string {
    if ($t === '') {
        return '';
    }
    return str_starts_with($t, '-') ? 'down' : 'up';
};

$metrics = [];
foreach (
    [
        ['revenue_ytd', 'green', true, 'revenue_ytd'],
        ['total_expenses', 'red', true, 'expenses_ytd'],
        ['net_profit_ytd', 'blue', true, 'net_profit_ytd'],
        ['inventory_value', 'purple', true, 'inventory_value'],
        ['new_customers', 'teal', false, 'new_customers'],
        ['unpaid_invoices', 'orange', false, 'unpaid_invoices'],
    ] as [$key, $tone, $money, $trendKey]
) {
    if (!$isAdmin && $companyId < 1 && in_array($key, ['revenue_ytd', 'net_profit_ytd', 'total_expenses'], true)) {
        continue;
    }
    $val = $m[$key] ?? 0;
    $display = $money ? number_format((float) $val, 2) . ' <small>SAR</small>' : (string) (int) $val;
    $trend = (string) ($trends[$trendKey] ?? $trends[$key] ?? '');
    $metrics[] = [
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
    ];
}
if ($isAdmin) {
    $actions[] = ['href' => rateb_url('admin/invoices/create'), 'label' => __('new_invoice'), 'icon' => 'fa-file-invoice'];
    $actions[] = ['href' => rateb_url('admin/journal-entries'), 'label' => __('journal_entries'), 'icon' => 'fa-book', 'primary' => true];
} else {
    if ($companyId > 0) {
        $actions[] = ['href' => rateb_app_url('accounting/reports'), 'label' => __('accounting_reports'), 'icon' => 'fa-chart-pie'];
        $actions[] = ['href' => rateb_app_url('accounting/cfo-dashboard'), 'label' => __('cfo_dashboard'), 'icon' => 'fa-chart-line'];
    }
    $actions[] = ['href' => rateb_app_url('journal-entries/create'), 'label' => __('new_journal_entry'), 'icon' => 'fa-plus', 'primary' => true];
}

Rateb\App\Core\View::partial('dashboard/head');
?>
<?php if (!$isAdmin && $companyId < 1 && rateb_is_super_admin()) { ?>
<div class="alert alert-warning mb-3 py-2 small"><?php echo __('accounting_select_company_hint'); ?></div>
<?php } ?>

<div class="cm cm--wide" data-cm-dash="v5c">
    <?php
    Rateb\App\Core\View::partial('dashboard/hero', [
        'tag' => __('accounting'),
        'title' => __('accounting_dashboard'),
        'subtitle' => __('accounting_dashboard_intro'),
        'actions' => $actions,
    ]);
    Rateb\App\Core\View::partial('dashboard/alerts', [
        'alerts' => $alerts,
        'empty' => __('accounting_no_alerts'),
    ]);
    Rateb\App\Core\View::partial('dashboard/metrics-strip', ['metrics' => $metrics]);
    ?>

    <div class="cm-body">
        <section class="cm-zone">
            <header class="cm-zone__bar"><h2><?php echo __('revenue_vs_expenses'); ?></h2></header>
            <div class="cm-chart cm-chart--xl">
                <canvas id="chart-revenue-expenses"
                    data-labels='<?php echo Rateb\App\Core\View::escape($revExpLabels); ?>'
                    data-revenue='<?php echo Rateb\App\Core\View::escape($revExpRevenue); ?>'
                    data-expenses='<?php echo Rateb\App\Core\View::escape($revExpExpenses); ?>'
                    data-label-revenue="<?php echo Rateb\App\Core\View::escape(__('revenue')); ?>"
                    data-label-expenses="<?php echo Rateb\App\Core\View::escape(__('total_expenses')); ?>"></canvas>
            </div>
        </section>

        <div class="cm-viz-grid cm-viz-grid--2">
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('revenue'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-acct-revenue" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('revenue')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($revOnlyLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($revOnlyValues); ?>'></canvas>
                </div>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('total_expenses'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-acct-expenses" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('total_expenses')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($expOnlyLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($expOnlyValues); ?>'></canvas>
                </div>
            </section>
        </div>

        <div class="cm-viz-grid cm-viz-grid--3">
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('expense_breakdown'); ?></h2></header>
                <?php if ($breakdown === []) { ?>
                <p class="cm-empty"><?php echo __('no_records'); ?></p>
                <?php } else { ?>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-expense-breakdown" data-labels='<?php echo Rateb\App\Core\View::escape($bdLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($bdValues); ?>'></canvas>
                </div>
                <?php } ?>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo $isAdmin && $companyId < 1 ? __('invoice_status') : __('ar_ap_balance'); ?></h2></header>
                <?php if (($charts['ar_ap'] ?? []) === []) { ?>
                <p class="cm-empty"><?php echo __('no_records'); ?></p>
                <?php } else { ?>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-acct-arap" data-labels='<?php echo Rateb\App\Core\View::escape($arApLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($arApValues); ?>'></canvas>
                </div>
                <?php } ?>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('journal_activity'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-journal-activity" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('journal_entries')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($journalLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($journalValues); ?>'></canvas>
                </div>
            </section>
        </div>

        <div class="cm-viz-grid cm-viz-grid--2">
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('top_customers'); ?></h2></header>
                <?php if ($topCustomers === []) { ?>
                <p class="cm-empty"><?php echo __('no_records'); ?></p>
                <?php } else { ?>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-top-customers" data-chart-type="horizontalBar"
                        data-labels='<?php echo Rateb\App\Core\View::escape(json_encode(array_column($topCustomers, 'name'))); ?>'
                        data-values='<?php echo Rateb\App\Core\View::escape(json_encode(array_map('floatval', array_column($topCustomers, 'total')))); ?>'
                        data-chart-label="<?php echo Rateb\App\Core\View::escape(__('revenue')); ?>"></canvas>
                </div>
                <?php } ?>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('top_sold_items'); ?></h2></header>
                <?php if ($topItems === []) { ?>
                <p class="cm-empty"><?php echo __('no_records'); ?></p>
                <?php } else { ?>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-top-items" data-chart-type="horizontalBar"
                        data-labels='<?php echo Rateb\App\Core\View::escape(json_encode(array_column($topItems, 'name'))); ?>'
                        data-values='<?php echo Rateb\App\Core\View::escape(json_encode(array_map('floatval', array_column($topItems, 'total')))); ?>'
                        data-chart-label="<?php echo Rateb\App\Core\View::escape(__('total')); ?>"></canvas>
                </div>
                <?php } ?>
            </section>
        </div>

        <div class="cm-viz-grid cm-viz-grid--2">
            <section class="cm-board cm-board--fill">
                <div class="cm-board__head"><?php echo __('recent_accounting_activity'); ?></div>
                <?php if ($recent === []) { ?>
                <p class="cm-empty"><?php echo __('no_records'); ?></p>
                <?php } else { ?>
                <ul class="cm-feed">
                    <?php foreach (array_slice($recent, 0, 8) as $row) {
                        $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : ($row['description'] ?? '');
                        ?>
                    <li>
                        <strong><?php echo Rateb\App\Core\View::escape((string) ($row['entry_no'] ?? '')); ?></strong>
                        — <?php echo Rateb\App\Core\View::escape((string) $desc); ?>
                        <time><?php echo Rateb\App\Core\View::escape(substr((string) ($row['entry_date'] ?? $row['issued_at'] ?? ''), 0, 16)); ?></time>
                    </li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </section>

            <section class="cm-board cm-board--fill">
                <div class="cm-board__head">
                    <span><?php echo __('trial_balance'); ?></span>
                    <?php if (!$isAdmin && rateb_can_export_entity('accounting')) { ?>
                    <a href="<?php echo rateb_app_url('accounting/export/trial-balance'); ?>"><?php echo __('export_trial_balance'); ?></a>
                    <?php } ?>
                </div>
                <table class="cm-tbl cm-tbl--dense">
                    <thead><tr><th><?php echo __('code'); ?></th><th><?php echo __('name'); ?></th><th class="text-end"><?php echo __('debit'); ?></th><th class="text-end"><?php echo __('credit'); ?></th></tr></thead>
                    <tbody>
                    <?php
                    $shown = 0;
                    if ($trial === []) {
                        echo '<tr><td colspan="4" class="text-center text-muted py-3">' . __('no_records') . '</td></tr>';
                    } else {
                        foreach ($trial as $row) {
                            if ($shown >= 8) {
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
                <p class="cm-note"><?php echo $isAdmin ? __('accounting_sync_help') : __('accounting_auto_post_help'); ?></p>
            </section>
        </div>
    </div>
</div>
