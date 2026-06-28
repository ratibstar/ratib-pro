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
<!-- rateb-dashboard-v4 -->
<div class="nx" data-nx-dash="v4">
    <?php
    Rateb\App\Core\View::partial('dashboard/hero', [
        'eyebrow' => __('platform_billing'),
        'title' => __('dashboard'),
        'subtitle' => __('platform_dashboard_intro') . ' · ' . date('Y-m-d'),
        'actions' => $actions,
        'metrics' => $metrics,
    ]);
    ?>

    <div class="nx-stage">
        <div class="nx-col">
            <div class="nx-mosaic">
                <div class="nx-glass" data-nx-chart-tabs>
                    <div class="nx-glass__top">
                        <span class="nx-glass__title"><?php echo __('company_growth'); ?></span>
                    </div>
                    <div class="nx-tabs" role="tablist">
                        <button type="button" class="nx-tab is-active" data-nx-chart-tab="companies" role="tab"><?php echo __('company_growth'); ?></button>
                        <button type="button" class="nx-tab" data-nx-chart-tab="subscriptions" role="tab"><?php echo __('subscription_growth'); ?></button>
                        <button type="button" class="nx-tab" data-nx-chart-tab="users" role="tab"><?php echo __('user_growth'); ?></button>
                    </div>
                    <div class="nx-tab-pane is-active" data-nx-chart-pane="companies">
                        <div class="nx-viz">
                            <canvas id="chart-companies" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($coValues); ?>'></canvas>
                        </div>
                    </div>
                    <div class="nx-tab-pane" data-nx-chart-pane="subscriptions">
                        <div class="nx-viz">
                            <canvas id="chart-subscriptions" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($subLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subValues); ?>'></canvas>
                        </div>
                    </div>
                    <div class="nx-tab-pane" data-nx-chart-pane="users">
                        <div class="nx-viz">
                            <canvas id="chart-users" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('user_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($userLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($userValues); ?>'></canvas>
                        </div>
                    </div>
                </div>
                <div class="nx-glass">
                    <div class="nx-glass__top">
                        <span class="nx-glass__title"><?php echo __('company_status_distribution'); ?></span>
                    </div>
                    <div class="nx-viz">
                        <canvas id="chart-company-status" data-labels='<?php echo Rateb\App\Core\View::escape($statusLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($statusValues); ?>'></canvas>
                    </div>
                </div>
            </div>

            <div class="nx-duo">
                <?php if ($recentCompanies !== []) { ?>
                <div class="nx-glass">
                    <div class="nx-glass__top">
                        <span class="nx-glass__title"><?php echo __('recent_companies'); ?></span>
                        <a href="<?php echo rateb_url('admin/companies'); ?>"><?php echo __('view_all'); ?></a>
                    </div>
                    <div class="nx-glass__body">
                        <ul class="nx-stream">
                            <?php foreach (array_slice($recentCompanies, 0, 6) as $row) { ?>
                            <li>
                                <span class="nx-stream__pip"></span>
                                <div>
                                    <a href="<?php echo rateb_url('admin/companies/' . (int) $row['id']); ?>"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></a>
                                    <span class="nx-tag ms-1"><?php echo __((string) ($row['status'] ?? '')); ?></span>
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
            <div class="nx-glass">
                <div class="nx-glass__top">
                    <span class="nx-glass__title"><?php echo __('recent_logins'); ?></span>
                </div>
                <div class="nx-glass__body nx-glass__body--0">
                    <table class="nx-table">
                        <thead><tr><th><?php echo __('email'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('date'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($recentLogins, 0, 6) as $row) { ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['email'] ?? '')); ?></td>
                            <td><?php if ((int) ($row['success'] ?? 0) === 1) { ?><span class="nx-tag nx-tag--ok"><?php echo __('login_success'); ?></span><?php } else { ?><span class="nx-tag nx-tag--bad"><?php echo __('failed'); ?></span><?php } ?></td>
                            <td class="text-muted"><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 16)); ?></td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>
        </div>

        <aside class="nx-rail">
            <?php Rateb\App\Core\View::partial('dashboard/alerts', ['alerts' => $alerts]); ?>
        </aside>
    </div>
</div>
<script src="<?php echo rateb_asset('js/dashboard-tabs.js'); ?>"></script>
