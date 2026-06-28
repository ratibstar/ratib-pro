<?php
/** @var array<string, mixed> $dash */
/** @var array<int, array<string, mixed>> $trial */
/** @var bool $isAdmin */
/** @var bool $canPost */
/** @var string $csrf */
$dash = $dash ?? [];
$m = $dash['metrics'] ?? [];
$kpis = $dash['kpis'] ?? [];
$charts = $dash['charts'] ?? [];
$alerts = $dash['alerts'] ?? [];
$recent = $dash['recent'] ?? [];
$trial = $trial ?? [];
$isAdmin = (bool) ($isAdmin ?? false);
$canPost = (bool) ($canPost ?? false);
$companyId = (int) ($selectedCompanyId ?? 0);

$revLabels = json_encode(array_column($charts['monthly_revenue'] ?? [], 'month'));
$revValues = json_encode(array_map('floatval', array_column($charts['monthly_revenue'] ?? [], 'total')));
$expLabels = json_encode(array_column($charts['monthly_expenses'] ?? [], 'month'));
$expValues = json_encode(array_map('floatval', array_column($charts['monthly_expenses'] ?? [], 'total')));
$arApLabels = json_encode(array_map(static fn ($r) => __((string) ($r['label'] ?? '')), $charts['ar_ap'] ?? []));
$arApValues = json_encode(array_map('floatval', array_column($charts['ar_ap'] ?? [], 'value')));

$widgets = [
    ['cash_position', 'fa-building-columns', 'primary', true],
    ['revenue_ytd', 'fa-coins', 'warning', true],
    ['net_profit_ytd', 'fa-chart-line', 'success', true],
    ['ar_open', 'fa-hand-holding-dollar', 'info', true],
    ['ap_open', 'fa-file-invoice-dollar', 'danger', true],
    ['invoices_open_total', 'fa-file-circle-exclamation', 'warning', true],
    ['payments_total', 'fa-money-bill-wave', 'success', false],
    ['journal_posted', 'fa-book', 'secondary', false],
];
?>
<link href="<?php echo rateb_asset('css/accounting-dashboard.css'); ?>" rel="stylesheet">

<?php if (!$isAdmin && $companyId < 1 && rateb_is_super_admin()) { ?>
<div class="alert alert-warning mb-3">
    <i class="fas fa-building me-1"></i> <?php echo __('accounting_select_company_hint'); ?>
</div>
<?php } ?>

<div class="rateb-acct-dash-header mb-4">
    <div>
        <h2 class="h5 mb-1"><i class="fas fa-calculator me-2 text-primary"></i><?php echo __('accounting_dashboard'); ?></h2>
        <p class="text-muted small mb-0"><?php echo __('accounting_dashboard_intro'); ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($canPost) { ?>
        <form method="post" action="<?php echo $isAdmin ? rateb_url('admin/accounting/sync') : rateb_app_url('accounting/sync'); ?>" class="d-inline">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> <?php echo __('accounting_sync'); ?></button>
        </form>
        <?php } ?>
        <?php if (!$isAdmin && $companyId > 0) { ?>
        <a href="<?php echo rateb_app_url('accounting/reports'); ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-chart-pie"></i> <?php echo __('accounting_reports'); ?>
        </a>
        <a href="<?php echo rateb_app_url('accounting/cfo-dashboard'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-user-tie"></i> <?php echo __('cfo_dashboard'); ?>
        </a>
        <?php } ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($widgets as $w) {
        if (!$isAdmin && $companyId < 1 && in_array($w[0], ['cash_position', 'revenue_ytd', 'net_profit_ytd', 'ar_open', 'ap_open'], true)) {
            continue;
        }
        $val = $m[$w[0]] ?? 0;
        if ($w[3]) {
            $val = number_format((float) $val, 2);
        }
        $labelKey = $w[0];
        if ($w[0] === 'invoices_open_total') {
            $labelKey = 'invoices_open';
        }
        ?>
    <div class="col-sm-6 col-xl-3">
        <div class="rateb-acct-widget">
            <div class="rateb-acct-widget-icon bg-<?php echo $w[2]; ?> bg-opacity-10 text-<?php echo $w[2]; ?>">
                <i class="fas <?php echo $w[1]; ?>"></i>
            </div>
            <div class="rateb-acct-widget-value"><?php echo Rateb\App\Core\View::escape((string) $val); ?><?php echo $w[3] ? ' <small>SAR</small>' : ''; ?></div>
            <div class="rateb-acct-widget-label"><?php echo __($labelKey); ?></div>
        </div>
    </div>
    <?php } ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="rateb-card rateb-chart-card h-100">
                    <div class="rateb-card-header"><?php echo __('monthly_revenue'); ?></div>
                    <div class="rateb-card-body">
                        <canvas id="chart-acct-revenue" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('monthly_revenue')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($revLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($revValues); ?>'></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="rateb-card rateb-chart-card h-100">
                    <div class="rateb-card-header"><?php echo __('monthly_expenses'); ?></div>
                    <div class="rateb-card-body">
                        <canvas id="chart-acct-expenses" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('monthly_expenses')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($expLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($expValues); ?>'></canvas>
                    </div>
                </div>
            </div>
            <?php if (!empty($charts['ar_ap'])) { ?>
            <div class="col-12">
                <div class="rateb-card rateb-chart-card">
                    <div class="rateb-card-header"><?php echo __('ar_ap_overview'); ?></div>
                    <div class="rateb-card-body" style="max-height:220px">
                        <canvas id="chart-acct-arap" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('ar_ap_overview')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($arApLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($arApValues); ?>'></canvas>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="rateb-acct-side-panels">
            <?php if ($kpis !== []) { ?>
            <div class="rateb-card mb-3">
                <div class="rateb-card-header"><i class="fas fa-list-check me-1"></i> <?php echo __('accounting_kpi_list'); ?></div>
                <div class="rateb-card-body p-0">
                    <ul class="rateb-acct-kpi-list list-unstyled mb-0">
                        <?php foreach ($kpis as $kpi) { ?>
                        <li class="rateb-acct-kpi-item">
                            <span class="rateb-acct-kpi-icon"><i class="fas <?php echo Rateb\App\Core\View::escape($kpi['icon'] ?? 'fa-circle'); ?>"></i></span>
                            <span class="rateb-acct-kpi-label"><?php echo __((string) ($kpi['label'] ?? '')); ?></span>
                            <span class="rateb-acct-kpi-value">
                                <?php echo Rateb\App\Core\View::escape((string) ($kpi['value'] ?? '')); ?>
                                <?php if (!empty($kpi['trend'])) { ?>
                                <small class="text-success ms-1"><?php echo Rateb\App\Core\View::escape((string) $kpi['trend']); ?></small>
                                <?php } ?>
                            </span>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
            <?php } ?>

            <div class="rateb-card mb-3">
                <div class="rateb-card-header"><i class="fas fa-bell me-1"></i> <?php echo __('smart_alerts'); ?></div>
                <div class="rateb-card-body">
                    <?php if ($alerts === []) { ?>
                    <p class="text-muted small mb-0"><i class="fas fa-check-circle text-success me-1"></i><?php echo __('accounting_no_alerts'); ?></p>
                    <?php } else { foreach ($alerts as $alert) {
                        $sev = (string) ($alert['severity'] ?? 'info');
                        ?>
                    <a href="<?php echo Rateb\App\Core\View::escape((string) ($alert['url'] ?? '#')); ?>" class="rateb-acct-alert rateb-acct-alert-<?php echo Rateb\App\Core\View::escape($sev); ?>">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?php echo Rateb\App\Core\View::escape((string) ($alert['message'] ?? '')); ?></span>
                    </a>
                    <?php } } ?>
                </div>
            </div>

            <div class="rateb-card">
                <div class="rateb-card-header"><i class="fas fa-bolt me-1"></i> <?php echo __('quick_shortcuts'); ?></div>
                <div class="rateb-card-body">
                    <div class="rateb-acct-shortcuts">
                        <?php if ($isAdmin) { ?>
                        <a href="<?php echo rateb_url('admin/invoices/create'); ?>" class="rateb-acct-shortcut"><i class="fas fa-file-invoice"></i><?php echo __('new_invoice'); ?></a>
                        <a href="<?php echo rateb_url('admin/payments/create'); ?>" class="rateb-acct-shortcut"><i class="fas fa-money-bill"></i><?php echo __('new_payment'); ?></a>
                        <a href="<?php echo rateb_url('admin/chart-of-accounts/create'); ?>" class="rateb-acct-shortcut"><i class="fas fa-plus"></i><?php echo __('add_account'); ?></a>
                        <a href="<?php echo rateb_url('admin/journal-entries'); ?>" class="rateb-acct-shortcut"><i class="fas fa-book"></i><?php echo __('journal_entries'); ?></a>
                        <?php } else { ?>
                        <a href="<?php echo rateb_app_url('journal-entries/create'); ?>" class="rateb-acct-shortcut"><i class="fas fa-book"></i><?php echo __('new_journal_entry'); ?></a>
                        <a href="<?php echo rateb_app_url('cash-vouchers/create'); ?>" class="rateb-acct-shortcut"><i class="fas fa-money-bill-wave"></i><?php echo __('new_cash_voucher'); ?></a>
                        <a href="<?php echo rateb_app_url('accounting/supplier-payments/create'); ?>" class="rateb-acct-shortcut"><i class="fas fa-hand-holding-dollar"></i><?php echo __('supplier_payment'); ?></a>
                        <a href="<?php echo rateb_app_url('customers/create'); ?>" class="rateb-acct-shortcut"><i class="fas fa-user-plus"></i><?php echo __('add_customer'); ?></a>
                        <a href="<?php echo rateb_app_url('accounting/accounts-receivable'); ?>" class="rateb-acct-shortcut"><i class="fas fa-file-invoice-dollar"></i><?php echo __('accounts_receivable'); ?></a>
                        <a href="<?php echo rateb_app_url('accounting/bank-reconciliation'); ?>" class="rateb-acct-shortcut"><i class="fas fa-scale-balanced"></i><?php echo __('bank_reconciliation'); ?></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($recent !== []) { ?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clock-rotate-left me-1"></i> <?php echo __('recent_accounting_activity'); ?></span>
        <?php if (!$isAdmin) { ?>
        <a href="<?php echo rateb_app_url('accounting/journal-register'); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('journal_register'); ?></a>
        <?php } ?>
    </div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('entry_no'); ?></th>
                    <th><?php echo __('date'); ?></th>
                    <th><?php echo __('description'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th class="text-end"><?php echo __('amount'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $row) {
                    $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : ($row['description'] ?? '');
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['entry_no'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['entry_date'] ?? $row['issued_at'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) $desc); ?></td>
                    <td><span class="badge bg-secondary"><?php echo __((string) ($row['status'] ?? '')); ?></span></td>
                    <td class="text-end"><?php echo number_format((float) ($row['total_debit'] ?? $row['total_amount'] ?? 0), 2); ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo __('trial_balance'); ?></span>
        <?php if (!$isAdmin && rateb_can_export_entity('accounting')) { ?>
        <a href="<?php echo rateb_app_url('accounting/export/trial-balance'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-download"></i> <?php echo __('export_trial_balance'); ?>
        </a>
        <?php } ?>
    </div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('account_type'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($trial === []) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    $shown = 0;
                    foreach ($trial as $row) {
                        if ($shown >= 15) {
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
                    <td><?php echo __((string) ($row['account_type'] ?? '')); ?></td>
                    <td class="text-end"><?php echo number_format($dr, 2); ?></td>
                    <td class="text-end"><?php echo number_format($cr, 2); ?></td>
                </tr>
                <?php }
                    if ($shown === 0) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="text-muted small mt-3 mb-0"><?php echo $isAdmin ? __('accounting_sync_help') : __('accounting_auto_post_help'); ?></p>
