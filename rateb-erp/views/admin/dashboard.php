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
$planLabels = json_encode(array_column($c['plan_distribution'] ?? [], 'label'));
$planValues = json_encode(array_map('intval', array_column($c['plan_distribution'] ?? [], 'value')));
$subStatLabels = json_encode(array_map(static fn ($r) => __((string) ($r['label'] ?? '')), $c['subscription_status'] ?? []));
$subStatValues = json_encode(array_map('intval', array_column($c['subscription_status'] ?? [], 'value')));
$loginLabels = json_encode(array_column($c['login_activity'] ?? [], 'month'));
$loginSuccess = json_encode(array_map('intval', array_column($c['login_activity'] ?? [], 'success_total')));
$loginFailed = json_encode(array_map('intval', array_column($c['login_activity'] ?? [], 'failed_total')));

$metrics = [];
foreach (
    [
        ['total_companies', 'blue'],
        ['active_companies', 'green'],
        ['subscriptions', 'purple'],
        ['users', 'teal'],
        ['pending_companies', 'orange'],
        ['expiring_subscriptions', 'red'],
    ] as [$key, $tone]
) {
    $metrics[] = ['label' => __($key), 'value' => (int) ($m[$key] ?? 0), 'tone' => $tone];
}

$actions = [];
if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
    $actions[] = ['href' => rateb_url('admin/companies/create'), 'label' => __('add_company'), 'icon' => 'fa-plus'];
    if (rateb_nav_can('companies.view')) {
        $actions[] = ['href' => rateb_url('admin/company-permissions'), 'label' => __('company_permissions'), 'icon' => 'fa-sliders'];
    }
}
$actions[] = ['href' => rateb_url('admin/users/create'), 'label' => __('add_user'), 'icon' => 'fa-user-plus'];
if (rateb_nav_can('accounting.view', 'accounting')) {
    $accountingHref = (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host())
        ? rateb_url('admin/accounting')
        : rateb_app_url('accounting');
    $actions[] = ['href' => $accountingHref, 'label' => __('accounting_dashboard'), 'icon' => 'fa-calculator', 'primary' => true];
}

$rankRows = array_map(static fn ($r) => [
    'name' => (string) ($r['company_name'] ?? ''),
    'total' => (int) ($r['user_count'] ?? 0),
    'suffix' => __('users'),
], $topCompanies);

Rateb\App\Core\View::partial('dashboard/head');
?>
<!-- rateb-dashboard-v5-charts -->
<div class="cm cm--wide" data-cm-dash="v5c"
     data-rateb-chartjs="<?php echo Rateb\App\Core\View::escape(rateb_chartjs('4.4.3')); ?>"
     data-rateb-charts="<?php echo Rateb\App\Core\View::escape(rateb_asset('js/charts.js')); ?>"<?php
if (!empty($dashboardChartsUrl)) {
    echo ' data-charts-url="' . Rateb\App\Core\View::escape((string) $dashboardChartsUrl) . '"';
}
?>>
    <?php
    Rateb\App\Core\View::partial('dashboard/hero', [
        'tag' => __('platform_billing'),
        'title' => __('dashboard'),
        'subtitle' => __('platform_dashboard_intro') . ' · ' . date('Y-m-d'),
        'actions' => $actions,
    ]);
    Rateb\App\Core\View::partial('dashboard/alerts', ['alerts' => $alerts]);
    Rateb\App\Core\View::partial('dashboard/metrics-strip', ['metrics' => $metrics]);
    ?>

    <div class="cm-body">
        <div class="cm-viz-grid cm-viz-grid--3">
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('company_growth'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-companies" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($coValues); ?>'></canvas>
                </div>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('subscription_growth'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-subscriptions" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($subLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subValues); ?>'></canvas>
                </div>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('user_growth'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-users" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('user_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($userLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($userValues); ?>'></canvas>
                </div>
            </section>
        </div>

        <div class="cm-viz-grid cm-viz-grid--2">
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('platform_overview'); ?></h2></header>
                <div class="cm-chart cm-chart--xl">
                    <canvas id="chart-platform-overview"
                        data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>'
                        data-dataset-1='<?php echo Rateb\App\Core\View::escape($coValues); ?>'
                        data-dataset-2='<?php echo Rateb\App\Core\View::escape($subValues); ?>'
                        data-dataset-3='<?php echo Rateb\App\Core\View::escape($userValues); ?>'
                        data-label-1="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>"
                        data-label-2="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>"
                        data-label-3="<?php echo Rateb\App\Core\View::escape(__('user_growth')); ?>"></canvas>
                </div>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('company_status_distribution'); ?></h2></header>
                <div class="cm-chart cm-chart--xl">
                    <canvas id="chart-company-status" data-labels='<?php echo Rateb\App\Core\View::escape($statusLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($statusValues); ?>'></canvas>
                </div>
            </section>
        </div>

        <div class="cm-viz-grid cm-viz-grid--3">
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('plan_distribution'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-plan-distribution" data-labels='<?php echo Rateb\App\Core\View::escape($planLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($planValues); ?>'></canvas>
                </div>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('subscription_status'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-subscription-status" data-labels='<?php echo Rateb\App\Core\View::escape($subStatLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subStatValues); ?>'></canvas>
                </div>
            </section>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('login_activity'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-login-activity"
                        data-labels='<?php echo Rateb\App\Core\View::escape($loginLabels); ?>'
                        data-success='<?php echo Rateb\App\Core\View::escape($loginSuccess); ?>'
                        data-failed='<?php echo Rateb\App\Core\View::escape($loginFailed); ?>'
                        data-label-success="<?php echo Rateb\App\Core\View::escape(__('login_success')); ?>"
                        data-label-failed="<?php echo Rateb\App\Core\View::escape(__('failed')); ?>"></canvas>
                </div>
            </section>
        </div>

        <div class="cm-viz-grid cm-viz-grid--2">
            <?php if ($topCompanies !== []) { ?>
            <section class="cm-zone">
                <header class="cm-zone__bar"><h2><?php echo __('top_companies_users'); ?></h2></header>
                <div class="cm-chart cm-chart--lg">
                    <canvas id="chart-top-companies" data-chart-type="horizontalBar"
                        data-labels='<?php echo Rateb\App\Core\View::escape(json_encode(array_column($topCompanies, 'company_name'))); ?>'
                        data-values='<?php echo Rateb\App\Core\View::escape(json_encode(array_map('intval', array_column($topCompanies, 'user_count')))); ?>'
                        data-chart-label="<?php echo Rateb\App\Core\View::escape(__('users')); ?>"></canvas>
                </div>
            </section>
            <?php } ?>

            <?php if ($recentCompanies !== []) { ?>
            <section class="cm-board cm-board--fill">
                <div class="cm-board__head">
                    <span><?php echo __('recent_companies'); ?></span>
                    <a href="<?php echo rateb_url('admin/companies'); ?>"><?php echo __('view_all'); ?></a>
                </div>
                <table class="cm-tbl cm-tbl--dense">
                    <thead><tr><th><?php echo __('name'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('date'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($recentCompanies, 0, 6) as $row) { ?>
                    <tr>
                        <td><a href="<?php echo rateb_url('admin/companies/' . (int) $row['id']); ?>"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></a></td>
                        <td><span class="cm-badge cm-badge--muted"><?php echo __((string) ($row['status'] ?? '')); ?></span></td>
                        <td class="text-muted"><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 10)); ?></td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </section>
            <?php } ?>
        </div>

        <?php if ($recentLogins !== []) { ?>
        <section class="cm-board">
            <div class="cm-board__head"><?php echo __('recent_logins'); ?></div>
            <table class="cm-tbl cm-tbl--dense">
                <thead><tr><th><?php echo __('email'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('date'); ?></th></tr></thead>
                <tbody>
                <?php foreach (array_slice($recentLogins, 0, 8) as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['email'] ?? '')); ?></td>
                    <td><?php if ((int) ($row['success'] ?? 0) === 1) { ?><span class="cm-badge cm-badge--ok"><?php echo __('login_success'); ?></span><?php } else { ?><span class="cm-badge cm-badge--bad"><?php echo __('failed'); ?></span><?php } ?></td>
                    <td class="text-muted"><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 16)); ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </section>
        <?php } ?>
    </div>
</div>
<script src="<?php echo rateb_chartjs('4.4.3'); ?>" defer></script>
<script src="<?php echo rateb_asset('js/charts.js'); ?>" defer></script>
<?php if (!empty($dashboardChartsUrl)) { ?>
<script src="<?php echo rateb_asset('js/dashboard-charts-defer.js'); ?>" defer></script>
<?php } ?>
