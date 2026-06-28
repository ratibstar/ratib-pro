<?php
$dash = $dash ?? [];
$m = $dash['metrics'] ?? ($metrics ?? []);
$c = $dash['charts'] ?? ($charts ?? []);
$alerts = $dash['alerts'] ?? [];
$recentCompanies = $dash['recent_companies'] ?? [];
$recentLogins = $dash['recent_logins'] ?? [];

$coLabels = json_encode(array_column($c['company_growth'] ?? [], 'month'));
$coValues = json_encode(array_map('intval', array_column($c['company_growth'] ?? [], 'total')));
$subLabels = json_encode(array_column($c['subscription_growth'] ?? [], 'month'));
$subValues = json_encode(array_map('intval', array_column($c['subscription_growth'] ?? [], 'total')));
$userLabels = json_encode(array_column($c['user_growth'] ?? [], 'month'));
$userValues = json_encode(array_map('intval', array_column($c['user_growth'] ?? [], 'total')));

$kpis = [
    ['total_companies', ''],
    ['active_companies', ''],
    ['subscriptions', ''],
    ['users', ''],
];
if ((int) ($m['pending_companies'] ?? 0) > 0) {
    $kpis[] = ['pending_companies', 'rateb-dash-kpi-warn'];
}
if ((int) ($m['suspended_companies'] ?? 0) > 0) {
    $kpis[] = ['suspended_companies', 'rateb-dash-kpi-danger'];
}
if ((int) ($m['expiring_subscriptions'] ?? 0) > 0) {
    $kpis[] = ['expiring_subscriptions', 'rateb-dash-kpi-warn'];
}
if ((int) ($m['pending_approvals'] ?? 0) > 0) {
    $kpis[] = ['pending_approvals', 'rateb-dash-kpi-warn'];
}
?>
<link href="<?php echo rateb_asset('css/dashboard-modern.css'); ?>" rel="stylesheet">

<div class="rateb-dash">
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
            <?php if (rateb_nav_can('workflows.view')) { ?>
            <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>"><?php echo __('approvals_oversight'); ?></a>
            <?php } ?>
            <a href="<?php echo rateb_url('admin/subscriptions'); ?>"><?php echo __('subscriptions'); ?></a>
        </nav>
    </header>

    <div class="rateb-dash-kpi-row">
        <?php foreach ($kpis as [$key, $class]) { ?>
        <div class="rateb-dash-kpi <?php echo Rateb\App\Core\View::escape($class); ?>">
            <div class="rateb-dash-kpi-value"><?php echo (int) ($m[$key] ?? 0); ?></div>
            <div class="rateb-dash-kpi-label"><?php echo __($key); ?></div>
        </div>
        <?php } ?>
    </div>

    <div class="rateb-dash-grid">
        <section class="rateb-dash-panel" data-dash-chart-tabs>
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

        <aside class="rateb-dash-side-stack">
            <div class="rateb-dash-panel">
                <div class="rateb-dash-panel-head"><?php echo __('smart_alerts'); ?></div>
                <div class="rateb-dash-panel-body flush">
                    <?php if ($alerts === []) { ?>
                    <p class="rateb-dash-feed-empty"><?php echo __('dashboard_no_alerts'); ?></p>
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
                        <a href="<?php echo rateb_url('admin/companies/create'); ?>"><?php echo __('add_company'); ?></a>
                        <a href="<?php echo rateb_url('admin/users/create'); ?>"><?php echo __('add_user'); ?></a>
                        <?php if (rateb_nav_can('executive.dashboard.view')) { ?>
                        <a href="<?php echo rateb_url('admin/executive-dashboard'); ?>"><?php echo __('executive_dashboard'); ?></a>
                        <?php } ?>
                    </div>
                </div>
                <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
                <p class="rateb-dash-note"><?php echo __('dashboard_accounting_moved_hint'); ?></p>
                <?php } ?>
            </div>
        </aside>
    </div>

    <?php if ($recentCompanies !== [] || $recentLogins !== []) { ?>
    <div class="rateb-dash-two-col">
        <?php if ($recentCompanies !== []) { ?>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head d-flex justify-content-between align-items-center">
                <span><?php echo __('recent_companies'); ?></span>
                <a href="<?php echo rateb_url('admin/companies'); ?>" class="small text-decoration-none"><?php echo __('view_all'); ?></a>
            </div>
            <div class="rateb-dash-panel-body flush">
                <table class="rateb-dash-table">
                    <thead>
                    <tr>
                        <th><?php echo __('name'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('date'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentCompanies as $row) { ?>
                    <tr>
                        <td><a href="<?php echo rateb_url('admin/companies/' . (int) $row['id']); ?>"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></a></td>
                        <td><span class="rateb-dash-tag"><?php echo __((string) ($row['status'] ?? '')); ?></span></td>
                        <td class="text-muted"><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 10)); ?></td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php } ?>
        <?php if ($recentLogins !== []) { ?>
        <section class="rateb-dash-panel">
            <div class="rateb-dash-panel-head"><?php echo __('recent_logins'); ?></div>
            <div class="rateb-dash-panel-body flush">
                <table class="rateb-dash-table">
                    <thead>
                    <tr>
                        <th><?php echo __('email'); ?></th>
                        <th><?php echo __('status'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($recentLogins, 0, 6) as $row) { ?>
                    <tr>
                        <td><?php echo Rateb\App\Core\View::escape((string) ($row['email'] ?? '')); ?></td>
                        <td>
                            <?php if ((int) ($row['success'] ?? 0) === 1) { ?>
                            <span class="rateb-dash-tag ok"><?php echo __('login_success'); ?></span>
                            <?php } else { ?>
                            <span class="rateb-dash-tag bad"><?php echo __('failed'); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php } ?>
    </div>
    <?php } ?>
</div>

<script src="<?php echo rateb_asset('js/dashboard-tabs.js'); ?>"></script>
