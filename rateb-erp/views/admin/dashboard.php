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

Rateb\App\Core\View::partial('dashboard/head');
?>
<!-- rateb-dashboard-v5-command -->
<div class="cm" data-cm-dash="v5">
    <?php
    Rateb\App\Core\View::partial('dashboard/hero', [
        'tag' => __('platform_billing'),
        'title' => __('dashboard'),
        'subtitle' => __('platform_dashboard_intro') . ' · ' . date('Y-m-d'),
        'actions' => $actions,
    ]);
    Rateb\App\Core\View::partial('dashboard/alerts', ['alerts' => $alerts]);
    ?>

    <div class="cm-split">
        <?php Rateb\App\Core\View::partial('dashboard/metrics-rail', ['metrics' => $metrics]); ?>

        <main class="cm-work">
            <section class="cm-zone" data-cm-chart-tabs>
                <header class="cm-zone__bar">
                    <h2><?php echo __('analytics'); ?></h2>
                    <div class="cm-seg" role="tablist">
                        <button type="button" class="cm-seg__btn is-active" data-cm-chart-tab="companies" role="tab"><?php echo __('company_growth'); ?></button>
                        <button type="button" class="cm-seg__btn" data-cm-chart-tab="subscriptions" role="tab"><?php echo __('subscription_growth'); ?></button>
                        <button type="button" class="cm-seg__btn" data-cm-chart-tab="users" role="tab"><?php echo __('user_growth'); ?></button>
                    </div>
                </header>
                <div class="cm-pane is-active" data-cm-chart-pane="companies">
                    <div class="cm-chart">
                        <canvas id="chart-companies" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($coValues); ?>'></canvas>
                    </div>
                </div>
                <div class="cm-pane" data-cm-chart-pane="subscriptions">
                    <div class="cm-chart">
                        <canvas id="chart-subscriptions" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($subLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subValues); ?>'></canvas>
                    </div>
                </div>
                <div class="cm-pane" data-cm-chart-pane="users">
                    <div class="cm-chart">
                        <canvas id="chart-users" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('user_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($userLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($userValues); ?>'></canvas>
                    </div>
                </div>
            </section>

            <div class="cm-row2">
                <section class="cm-zone">
                    <header class="cm-zone__bar">
                        <h2><?php echo __('company_status_distribution'); ?></h2>
                    </header>
                    <div class="cm-chart cm-chart--sm">
                        <canvas id="chart-company-status" data-labels='<?php echo Rateb\App\Core\View::escape($statusLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($statusValues); ?>'></canvas>
                    </div>
                </section>

                <?php if ($recentCompanies !== []) { ?>
                <section class="cm-board">
                    <div class="cm-board__head">
                        <span><?php echo __('recent_companies'); ?></span>
                        <a href="<?php echo rateb_url('admin/companies'); ?>"><?php echo __('view_all'); ?></a>
                    </div>
                    <table class="cm-tbl">
                        <thead><tr><th><?php echo __('name'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('date'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($recentCompanies, 0, 5) as $row) { ?>
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

            <?php
            Rateb\App\Core\View::partial('dashboard/ranks', [
                'title' => __('top_companies_users'),
                'rows' => $rankRows,
            ]);
            ?>

            <?php if ($recentLogins !== []) { ?>
            <section class="cm-board">
                <div class="cm-board__head"><?php echo __('recent_logins'); ?></div>
                <table class="cm-tbl">
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
        </main>
    </div>
</div>
<script src="<?php echo rateb_asset('js/dashboard-tabs.js'); ?>"></script>
