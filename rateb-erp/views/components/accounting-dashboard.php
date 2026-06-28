<?php
/** @var array<string, mixed> $dash */
/** @var array<int, array<string, mixed>> $trial */
/** @var bool $isAdmin */
/** @var bool $canPost */
/** @var string $csrf */
$dash = $dash ?? [];
$m = $dash['metrics'] ?? [];
$alerts = $dash['alerts'] ?? [];
$recent = $dash['recent'] ?? [];
$trial = $trial ?? [];
$isAdmin = (bool) ($isAdmin ?? false);
$canPost = (bool) ($canPost ?? false);
$companyId = (int) ($selectedCompanyId ?? 0);
$charts = $dash['charts'] ?? [];

$revLabels = json_encode(array_column($charts['monthly_revenue'] ?? [], 'month'));
$revValues = json_encode(array_map('floatval', array_column($charts['monthly_revenue'] ?? [], 'total')));
$expLabels = json_encode(array_column($charts['monthly_expenses'] ?? [], 'month'));
$expValues = json_encode(array_map('floatval', array_column($charts['monthly_expenses'] ?? [], 'total')));

$primaryKpis = [
    ['revenue', true],
    ['purchase_requests', false],
    ['purchase_orders', false],
    ['inventory_value', true],
];
$financeKpis = [
    ['cash_position', true],
    ['revenue_ytd', true],
    ['net_profit_ytd', true],
    ['ar_open', true],
    ['ap_open', true],
    ['invoices_open_total', true, 'invoices_open'],
];
?>
<link href="<?php echo rateb_asset('css/dashboard-modern.css'); ?>" rel="stylesheet">

<?php if (!$isAdmin && $companyId < 1 && rateb_is_super_admin()) { ?>
<div class="alert alert-warning mb-3 py-2 small"><?php echo __('accounting_select_company_hint'); ?></div>
<?php } ?>

<div class="rateb-dash">
    <header class="rateb-dash-hero">
        <div>
            <h1 class="rateb-dash-hero-title"><?php echo __('accounting_dashboard'); ?></h1>
            <p class="rateb-dash-hero-sub"><?php echo __('accounting_dashboard_intro'); ?></p>
        </div>
        <nav class="rateb-dash-hero-actions">
            <?php if ($canPost) { ?>
            <form method="post" action="<?php echo $isAdmin ? rateb_url('admin/accounting/sync') : rateb_app_url('accounting/sync'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none"><?php echo __('accounting_sync'); ?></button>
            </form>
            <?php } ?>
            <?php if (!$isAdmin && $companyId > 0) { ?>
            <a href="<?php echo rateb_app_url('accounting/reports'); ?>"><?php echo __('accounting_reports'); ?></a>
            <a href="<?php echo rateb_app_url('accounting/cfo-dashboard'); ?>"><?php echo __('cfo_dashboard'); ?></a>
            <?php } ?>
        </nav>
    </header>

    <div class="rateb-dash-kpi-row">
        <?php foreach ($primaryKpis as $kpi) {
            [$key, $money] = $kpi;
            $val = $m[$key] ?? 0;
            if ($money) {
                $val = number_format((float) $val, 0);
            }
            ?>
        <div class="rateb-dash-kpi">
            <div class="rateb-dash-kpi-value"><?php echo Rateb\App\Core\View::escape((string) $val); ?></div>
            <div class="rateb-dash-kpi-label"><?php echo __($key); ?></div>
        </div>
        <?php } ?>
    </div>

    <?php if ($isAdmin || $companyId > 0) { ?>
    <div class="rateb-dash-kpi-row">
        <?php foreach ($financeKpis as $kpi) {
            [$key, $money, $labelKey] = array_pad($kpi, 3, $key);
            if (!$isAdmin && $companyId < 1 && in_array($key, ['cash_position', 'revenue_ytd', 'net_profit_ytd', 'ar_open', 'ap_open'], true)) {
                continue;
            }
            ?>
        <div class="rateb-dash-kpi">
            <div class="rateb-dash-kpi-value"><?php echo number_format((float) ($m[$key] ?? 0), $money ? 0 : 0); ?></div>
            <div class="rateb-dash-kpi-label"><?php echo __($labelKey); ?></div>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <div class="rateb-dash-grid mt-4">
        <section class="rateb-dash-panel" data-dash-chart-tabs>
            <div class="rateb-dash-chart-tabs">
                <button type="button" class="rateb-dash-chart-tab is-active" data-dash-chart-tab="revenue"><?php echo __('revenue'); ?></button>
                <button type="button" class="rateb-dash-chart-tab" data-dash-chart-tab="expenses"><?php echo __('monthly_expenses'); ?></button>
            </div>
            <div class="rateb-dash-chart-pane is-active" data-dash-chart-pane="revenue">
                <div class="rateb-dash-chart-wrap">
                    <canvas id="chart-revenue" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('revenue')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($revLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($revValues); ?>'></canvas>
                </div>
            </div>
            <div class="rateb-dash-chart-pane" data-dash-chart-pane="expenses">
                <div class="rateb-dash-chart-wrap">
                    <canvas id="chart-acct-expenses" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('monthly_expenses')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($expLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($expValues); ?>'></canvas>
                </div>
            </div>
        </section>

        <aside class="rateb-dash-side-stack">
            <div class="rateb-dash-panel">
                <div class="rateb-dash-panel-head"><?php echo __('smart_alerts'); ?></div>
                <div class="rateb-dash-panel-body flush">
                    <?php if ($alerts === []) { ?>
                    <p class="rateb-dash-feed-empty"><?php echo __('accounting_no_alerts'); ?></p>
                    <?php } else { ?>
                    <ul class="rateb-dash-feed">
                        <?php foreach ($alerts as $alert) { ?>
                        <li><a href="<?php echo Rateb\App\Core\View::escape((string) ($alert['url'] ?? '#')); ?>"><?php echo Rateb\App\Core\View::escape((string) ($alert['message'] ?? '')); ?></a></li>
                        <?php } ?>
                    </ul>
                    <?php } ?>
                </div>
            </div>
            <div class="rateb-dash-panel">
                <div class="rateb-dash-panel-head"><?php echo __('quick_shortcuts'); ?></div>
                <div class="rateb-dash-panel-body">
                    <div class="rateb-dash-links">
                        <?php if ($isAdmin) { ?>
                        <a href="<?php echo rateb_url('admin/invoices/create'); ?>"><?php echo __('new_invoice'); ?></a>
                        <a href="<?php echo rateb_url('admin/journal-entries'); ?>"><?php echo __('journal_entries'); ?></a>
                        <?php } else { ?>
                        <a href="<?php echo rateb_app_url('journal-entries/create'); ?>"><?php echo __('new_journal_entry'); ?></a>
                        <a href="<?php echo rateb_app_url('cash-vouchers/create'); ?>"><?php echo __('new_cash_voucher'); ?></a>
                        <a href="<?php echo rateb_app_url('accounting/accounts-receivable'); ?>"><?php echo __('accounts_receivable'); ?></a>
                        <a href="<?php echo rateb_app_url('accounting/bank-reconciliation'); ?>"><?php echo __('bank_reconciliation'); ?></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <?php if ($recent !== []) { ?>
    <section class="rateb-dash-panel mt-4">
        <div class="rateb-dash-panel-head"><?php echo __('recent_accounting_activity'); ?></div>
        <div class="rateb-dash-panel-body flush">
            <table class="rateb-dash-table">
                <thead>
                <tr>
                    <th><?php echo __('entry_no'); ?></th>
                    <th><?php echo __('date'); ?></th>
                    <th><?php echo __('description'); ?></th>
                    <th class="text-end"><?php echo __('amount'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $row) {
                    $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : ($row['description'] ?? '');
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['entry_no'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape(substr((string) ($row['entry_date'] ?? $row['issued_at'] ?? ''), 0, 10)); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) $desc); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['total_debit'] ?? $row['total_amount'] ?? 0), 2); ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
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
                <thead>
                <tr>
                    <th><?php echo __('code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $shown = 0;
                if ($trial === []) {
                    echo '<tr><td colspan="4" class="text-center text-muted py-3">' . __('no_records') . '</td></tr>';
                } else {
                    foreach ($trial as $row) {
                        if ($shown >= 12) {
                            break;
                        }
                        $dr = (float) ($row['total_debit'] ?? 0);
                        $cr = (float) ($row['total_credit'] ?? 0);
                        if ($dr <= 0 && $cr <= 0) {
                            continue;
                        }
                        $shown++;
                        $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : $row['name'];
                        ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['code']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                    <td class="text-end"><?php echo number_format($dr, 2); ?></td>
                    <td class="text-end"><?php echo number_format($cr, 2); ?></td>
                </tr>
                <?php }
                    if ($shown === 0) {
                        echo '<tr><td colspan="4" class="text-center text-muted py-3">' . __('no_records') . '</td></tr>';
                    }
                } ?>
                </tbody>
            </table>
        </div>
        <p class="rateb-dash-note"><?php echo $isAdmin ? __('accounting_sync_help') : __('accounting_auto_post_help'); ?></p>
    </section>
</div>

<script src="<?php echo rateb_asset('js/dashboard-tabs.js'); ?>"></script>
