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
<!-- rateb-dashboard-v3 -->
<div class="rp">
    <?php
    Rateb\App\Core\View::partial('dashboard/hero', [
        'title' => __('dashboard'),
        'subtitle' => __('platform_dashboard_intro') . ' · ' . date('Y-m-d'),
        'actions' => $actions,
        'metrics' => $metrics,
    ]);
    ?>

    <div class="rp-body">
        <div class="rp-main">
            <div class="rp-bento">
                <div class="rp-tile rp-tile--8" data-rp-chart-tabs>
                    <div class="rp-tile__head"><?php echo __('company_growth'); ?></div>
                    <div class="rp-chart-tabs" role="tablist">
                        <button type="button" class="rp-chart-tab is-active" data-rp-chart-tab="companies" role="tab"><?php echo __('company_growth'); ?></button>
                        <button type="button" class="rp-chart-tab" data-rp-chart-tab="subscriptions" role="tab"><?php echo __('subscription_growth'); ?></button>
                        <button type="button" class="rp-chart-tab" data-rp-chart-tab="users" role="tab"><?php echo __('user_growth'); ?></button>
                    </div>
                    <div class="rp-chart-pane is-active" data-rp-chart-pane="companies">
                        <div class="rp-chart-well">
                            <canvas id="chart-companies" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($coValues); ?>'></canvas>
                        </div>
                    </div>
                    <div class="rp-chart-pane" data-rp-chart-pane="subscriptions">
                        <div class="rp-chart-well">
                            <canvas id="chart-subscriptions" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($subLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subValues); ?>'></canvas>
                        </div>
                    </div>
                    <div class="rp-chart-pane" data-rp-chart-pane="users">
                        <div class="rp-chart-well">
                            <canvas id="chart-users" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('user_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($userLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($userValues); ?>'></canvas>
                        </div>
                    </div>
                </div>
                <div class="rp-tile rp-tile--4">
                    <div class="rp-tile__head"><?php echo __('company_status_distribution'); ?></div>
                    <div class="rp-chart-well">
                        <canvas id="chart-company-status" data-labels='<?php echo Rateb\App\Core\View::escape($statusLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($statusValues); ?>'></canvas>
                    </div>
                </div>

                <?php if ($recentCompanies !== []) { ?>
                <div class="rp-tile rp-tile--6">
                    <div class="rp-tile__head">
                        <span><?php echo __('recent_companies'); ?></span>
                        <a href="<?php echo rateb_url('admin/companies'); ?>"><?php echo __('view_all'); ?></a>
                    </div>
                    <div class="rp-tile__body">
                        <ul class="rp-stream">
                            <?php foreach (array_slice($recentCompanies, 0, 6) as $row) { ?>
                            <li>
                                <span class="rp-stream__dot"></span>
                                <div>
                                    <a href="<?php echo rateb_url('admin/companies/' . (int) $row['id']); ?>"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></a>
                                    <span class="rp-pill ms-1"><?php echo __((string) ($row['status'] ?? '')); ?></span>
                                    <time><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 10)); ?></time>
                                </div>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
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
            <div class="rp-tile rp-tile--12">
                <div class="rp-tile__head"><?php echo __('recent_logins'); ?></div>
                <div class="rp-tile__body rp-tile__body--flush">
                    <table class="rp-table">
                        <thead><tr><th><?php echo __('email'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('date'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($recentLogins, 0, 6) as $row) { ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['email'] ?? '')); ?></td>
                            <td><?php if ((int) ($row['success'] ?? 0) === 1) { ?><span class="rp-pill rp-pill--ok"><?php echo __('login_success'); ?></span><?php } else { ?><span class="rp-pill rp-pill--bad"><?php echo __('failed'); ?></span><?php } ?></td>
                            <td class="text-muted"><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 16)); ?></td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>
        </div>

        <aside class="rp-rail">
            <?php Rateb\App\Core\View::partial('dashboard/alerts', ['alerts' => $alerts]); ?>
        </aside>
    </div>
</div>
<script src="<?php echo rateb_asset('js/dashboard-tabs.js'); ?>"></script>
