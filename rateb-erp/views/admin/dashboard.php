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

$kpis = [];
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
    $kpis[] = ['label' => __($key), 'value' => (int) ($m[$key] ?? 0), 'tone' => $tone];
}

$actions = [
    ['href' => rateb_url('admin/companies/create'), 'label' => __('add_company'), 'icon' => 'fa-plus'],
    ['href' => rateb_url('admin/users/create'), 'label' => __('add_user'), 'icon' => 'fa-user-plus'],
];
if (rateb_nav_can('accounting.view', 'accounting')) {
    $actions[] = ['href' => rateb_url('admin/accounting'), 'label' => __('accounting_dashboard'), 'icon' => 'fa-calculator', 'primary' => true];
}

$rankRows = array_map(static fn ($r) => [
    'name' => (string) ($r['company_name'] ?? ''),
    'total' => (int) ($r['user_count'] ?? 0),
    'suffix' => __('users'),
], $topCompanies);
$maxRank = max(1, ...array_map(static fn ($r) => (int) $r['total'], $rankRows ?: [['total' => 1]]));

Rateb\App\Core\View::partial('dashboard/head');
?>
<div class="rdx">
    <?php
    Rateb\App\Core\View::partial('dashboard/top', [
        'title' => __('dashboard'),
        'subtitle' => __('platform_dashboard_intro') . ' · ' . date('Y-m-d'),
        'actions' => $actions,
    ]);
    Rateb\App\Core\View::partial('dashboard/kpis', ['items' => $kpis]);
    ?>

    <div class="rdx-layout">
        <div class="rdx-stack">
            <div class="rdx-row-charts">
                <div class="rdx-card" data-rdx-chart-tabs>
                    <div class="rdx-card-head"><?php echo __('company_growth'); ?></div>
                    <div class="rdx-chart-tabs" role="tablist">
                        <button type="button" class="rdx-chart-tab is-active" data-rdx-chart-tab="companies" role="tab"><?php echo __('company_growth'); ?></button>
                        <button type="button" class="rdx-chart-tab" data-rdx-chart-tab="subscriptions" role="tab"><?php echo __('subscription_growth'); ?></button>
                        <button type="button" class="rdx-chart-tab" data-rdx-chart-tab="users" role="tab"><?php echo __('user_growth'); ?></button>
                    </div>
                    <div class="rdx-chart-pane is-active" data-rdx-chart-pane="companies">
                        <div class="rdx-chart">
                            <canvas id="chart-companies" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($coValues); ?>'></canvas>
                        </div>
                    </div>
                    <div class="rdx-chart-pane" data-rdx-chart-pane="subscriptions">
                        <div class="rdx-chart">
                            <canvas id="chart-subscriptions" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($subLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subValues); ?>'></canvas>
                        </div>
                    </div>
                    <div class="rdx-chart-pane" data-rdx-chart-pane="users">
                        <div class="rdx-chart">
                            <canvas id="chart-users" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('user_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($userLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($userValues); ?>'></canvas>
                        </div>
                    </div>
                </div>
                <div class="rdx-card">
                    <div class="rdx-card-head"><?php echo __('company_status_distribution'); ?></div>
                    <div class="rdx-chart">
                        <canvas id="chart-company-status" data-labels='<?php echo Rateb\App\Core\View::escape($statusLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($statusValues); ?>'></canvas>
                    </div>
                </div>
            </div>

            <div class="rdx-row-2">
                <?php if ($recentCompanies !== []) { ?>
                <div class="rdx-card">
                    <div class="rdx-card-head">
                        <span><?php echo __('recent_companies'); ?></span>
                        <a href="<?php echo rateb_url('admin/companies'); ?>"><?php echo __('view_all'); ?></a>
                    </div>
                    <ul class="rdx-feed">
                        <?php foreach (array_slice($recentCompanies, 0, 6) as $row) { ?>
                        <li>
                            <span class="rdx-feed-dot"></span>
                            <div class="rdx-feed-body">
                                <a href="<?php echo rateb_url('admin/companies/' . (int) $row['id']); ?>"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></a>
                                <span class="rdx-badge ms-1"><?php echo __((string) ($row['status'] ?? '')); ?></span>
                                <time><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 10)); ?></time>
                            </div>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
                <?php } ?>
                <?php
                Rateb\App\Core\View::partial('dashboard/ranks', [
                    'title' => __('top_companies_users'),
                    'rows' => $rankRows,
                    'max' => $maxRank,
                ]);
                ?>
            </div>

            <?php if ($recentLogins !== []) { ?>
            <div class="rdx-card">
                <div class="rdx-card-head"><?php echo __('recent_logins'); ?></div>
                <div class="rdx-card-body rdx-card-body--flush">
                    <table class="rdx-table">
                        <thead><tr><th><?php echo __('email'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('date'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($recentLogins, 0, 6) as $row) { ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['email'] ?? '')); ?></td>
                            <td><?php if ((int) ($row['success'] ?? 0) === 1) { ?><span class="rdx-badge rdx-badge--ok"><?php echo __('login_success'); ?></span><?php } else { ?><span class="rdx-badge rdx-badge--bad"><?php echo __('failed'); ?></span><?php } ?></td>
                            <td class="text-muted"><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 16)); ?></td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>
        </div>

        <aside class="rdx-stack">
            <?php Rateb\App\Core\View::partial('dashboard/alerts', ['alerts' => $alerts]); ?>
        </aside>
    </div>
</div>
<script src="<?php echo rateb_asset('js/dashboard-tabs.js'); ?>"></script>
