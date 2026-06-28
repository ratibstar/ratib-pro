<?php
$dash = $dash ?? [];
$m = $dash['metrics'] ?? ($metrics ?? []);
$c = $dash['charts'] ?? ($charts ?? []);
$alerts = $dash['alerts'] ?? [];
$recentCompanies = $dash['recent_companies'] ?? [];
$recentLogins = $dash['recent_logins'] ?? [];
$topCompanies = $dash['top_companies'] ?? [];

$coLabels = json_encode(array_column($c['company_growth'] ?? [], 'month'));
$coValues = json_encode(array_map('intval', array_column($c['company_growth'] ?? [], 'total')));
$subLabels = json_encode(array_column($c['subscription_growth'] ?? [], 'month'));
$subValues = json_encode(array_map('intval', array_column($c['subscription_growth'] ?? [], 'total')));
$userLabels = json_encode(array_column($c['user_growth'] ?? [], 'month'));
$userValues = json_encode(array_map('intval', array_column($c['user_growth'] ?? [], 'total')));
$statusLabels = json_encode(array_map(static fn ($r) => __((string) ($r['label'] ?? '')), $c['company_status'] ?? []));
$statusValues = json_encode(array_map('intval', array_column($c['company_status'] ?? [], 'value')));

$kpiCards = [
    ['total_companies', 'tone-blue', 'fa-building'],
    ['active_companies', 'tone-green', 'fa-circle-check'],
    ['subscriptions', 'tone-purple', 'fa-credit-card'],
    ['users', 'tone-teal', 'fa-users'],
    ['pending_companies', 'tone-orange', 'fa-hourglass-half'],
    ['expiring_subscriptions', 'tone-red', 'fa-calendar-xmark'],
];

$maxCo = max(1, ...array_map(static fn ($r) => (int) ($r['user_count'] ?? 0), $topCompanies ?: [['user_count' => 1]]));
?>
<link href="<?php echo rateb_asset('css/dashboard-modern.css'); ?>" rel="stylesheet">

<div class="rateb-dash rateb-dash--platform">
    <header class="rateb-dash-hero">
        <div>
            <h1 class="rateb-dash-hero-title"><?php echo __('dashboard'); ?></h1>
            <p class="rateb-dash-hero-sub"><?php echo __('platform_dashboard_intro'); ?> · <?php echo date('Y-m-d'); ?></p>
        </div>
        <nav class="rateb-dash-hero-actions" aria-label="<?php echo __('quick_shortcuts'); ?>">
            <a href="<?php echo rateb_url('admin/companies'); ?>"><?php echo __('companies'); ?></a>
            <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
            <a href="<?php echo rateb_url('admin/accounting'); ?>"><?php echo __('accounting_dashboard'); ?></a>
            <?php } ?>
            <a href="<?php echo rateb_url('admin/subscriptions'); ?>"><?php echo __('subscriptions'); ?></a>
        </nav>
    </header>

    <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
    <div class="rateb-dash-accounting-banner">
        <i class="fas fa-calculator text-primary"></i>
        <span><?php echo __('dashboard_accounting_moved_hint'); ?></span>
        <a href="<?php echo rateb_url('admin/accounting'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('accounting_dashboard'); ?></a>
    </div>
    <?php } ?>

    <div class="rateb-dash-kpi-cards">
        <?php foreach ($kpiCards as [$key, $tone, $icon]) { ?>
        <div class="rateb-dash-kpi-card">
            <div class="rateb-dash-kpi-card-icon <?php echo Rateb\App\Core\View::escape($tone); ?>"><i class="fas <?php echo Rateb\App\Core\View::escape($icon); ?>"></i></div>
            <div class="rateb-dash-kpi-card-value"><?php echo (int) ($m[$key] ?? 0); ?></div>
            <div class="rateb-dash-kpi-card-label"><?php echo __($key); ?></div>
        </div>
        <?php } ?>
    </div>

    <div class="rateb-dash-mid-row">
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('quick_shortcuts'); ?></div>
            <div class="rateb-dash-shortcuts-row">
                <a href="<?php echo rateb_url('admin/companies/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-building"></i></span><?php echo __('add_company'); ?></a>
                <a href="<?php echo rateb_url('admin/users/create'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-user-plus"></i></span><?php echo __('add_user'); ?></a>
                <a href="<?php echo rateb_url('admin/subscriptions'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-credit-card"></i></span><?php echo __('subscriptions'); ?></a>
                <?php if (rateb_nav_can('workflows.view')) { ?>
                <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-clipboard-check"></i></span><?php echo __('approvals_oversight'); ?></a>
                <?php } ?>
                <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
                <a href="<?php echo rateb_url('admin/accounting'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-calculator"></i></span><?php echo __('accounting'); ?></a>
                <?php } ?>
                <?php if (rateb_nav_can('executive.dashboard.view')) { ?>
                <a href="<?php echo rateb_url('admin/executive-dashboard'); ?>" class="rateb-dash-sc"><span class="rateb-dash-sc-icon"><i class="fas fa-chart-pie"></i></span><?php echo __('executive_dashboard'); ?></a>
                <?php } ?>
            </div>
        </section>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('smart_alerts'); ?></div>
            <div class="rateb-dash-panel-body flush">
                <?php if ($alerts === []) { ?>
                <p class="rateb-dash-feed-empty"><?php echo __('dashboard_no_alerts'); ?></p>
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
        <section class="rateb-dash-panel" data-dash-chart-tabs>
            <div class="rateb-dash-panel-head"><?php echo __('company_growth'); ?></div>
            <div class="rateb-dash-chart-tabs" role="tablist">
                <button type="button" class="rateb-dash-chart-tab is-active" data-dash-chart-tab="companies" role="tab"><?php echo __('company_growth'); ?></button>
                <button type="button" class="rateb-dash-chart-tab" data-dash-chart-tab="subscriptions" role="tab"><?php echo __('subscription_growth'); ?></button>
                <button type="button" class="rateb-dash-chart-tab" data-dash-chart-tab="users" role="tab"><?php echo __('user_growth'); ?></button>
            </div>
            <div class="rateb-dash-chart-pane is-active" data-dash-chart-pane="companies">
                <div class="rateb-dash-chart-wrap">
                    <canvas id="chart-companies" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($coValues); ?>'></canvas>
                </div>
            </div>
            <div class="rateb-dash-chart-pane" data-dash-chart-pane="subscriptions">
                <div class="rateb-dash-chart-wrap">
                    <canvas id="chart-subscriptions" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($subLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subValues); ?>'></canvas>
                </div>
            </div>
            <div class="rateb-dash-chart-pane" data-dash-chart-pane="users">
                <div class="rateb-dash-chart-wrap">
                    <canvas id="chart-users" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('user_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($userLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($userValues); ?>'></canvas>
                </div>
            </div>
        </section>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('company_status_distribution'); ?></div>
            <div class="rateb-dash-chart-wrap">
                <canvas id="chart-company-status" data-labels='<?php echo Rateb\App\Core\View::escape($statusLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($statusValues); ?>'></canvas>
            </div>
        </section>
    </div>

    <div class="rateb-dash-two-col">
        <?php if ($recentCompanies !== []) { ?>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head d-flex justify-content-between align-items-center">
                <span><?php echo __('recent_companies'); ?></span>
                <a href="<?php echo rateb_url('admin/companies'); ?>" class="small text-decoration-none"><?php echo __('view_all'); ?></a>
            </div>
            <ul class="rateb-dash-timeline">
                <?php foreach (array_slice($recentCompanies, 0, 6) as $row) { ?>
                <li>
                    <a href="<?php echo rateb_url('admin/companies/' . (int) $row['id']); ?>" class="text-decoration-none"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></a>
                    <span class="rateb-dash-tag ms-1"><?php echo __((string) ($row['status'] ?? '')); ?></span>
                    <time><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 10)); ?></time>
                </li>
                <?php } ?>
            </ul>
        </section>
        <?php } ?>
        <?php if ($topCompanies !== []) { ?>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('top_companies_users'); ?></div>
            <ol class="rateb-dash-rank-list">
                <?php foreach ($topCompanies as $row) {
                    $pct = min(100, ((int) ($row['user_count'] ?? 0) / $maxCo) * 100);
                    ?>
                <li class="rateb-dash-rank-item">
                    <div class="rateb-dash-rank-head">
                        <span class="rateb-dash-rank-name"><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '')); ?></span>
                        <span class="rateb-dash-rank-val"><?php echo (int) ($row['user_count'] ?? 0); ?> <?php echo __('users'); ?></span>
                    </div>
                    <div class="rateb-dash-rank-bar"><div class="rateb-dash-rank-fill" style="width:<?php echo $pct; ?>%"></div></div>
                </li>
                <?php } ?>
            </ol>
        </section>
        <?php } ?>
    </div>

    <?php if ($recentLogins !== []) { ?>
    <section class="rateb-dash-panel mt-4">
        <div class="rateb-dash-panel-head"><?php echo __('recent_logins'); ?></div>
        <div class="rateb-dash-panel-body flush">
            <table class="rateb-dash-table">
                <thead><tr><th><?php echo __('email'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('date'); ?></th></tr></thead>
                <tbody>
                <?php foreach (array_slice($recentLogins, 0, 6) as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['email'] ?? '')); ?></td>
                    <td><?php if ((int) ($row['success'] ?? 0) === 1) { ?><span class="rateb-dash-tag ok"><?php echo __('login_success'); ?></span><?php } else { ?><span class="rateb-dash-tag bad"><?php echo __('failed'); ?></span><?php } ?></td>
                    <td class="text-muted"><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 16)); ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php } ?>
</div>

<script src="<?php echo rateb_asset('js/dashboard-tabs.js'); ?>"></script>
