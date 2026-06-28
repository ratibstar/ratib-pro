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

$widgets = [
    ['total_companies', 'fa-building', 'primary'],
    ['active_companies', 'fa-circle-check', 'success'],
    ['pending_companies', 'fa-hourglass-half', 'warning'],
    ['suspended_companies', 'fa-ban', 'danger'],
    ['subscriptions', 'fa-credit-card', 'info'],
    ['expiring_subscriptions', 'fa-calendar-xmark', 'warning'],
    ['users', 'fa-users', 'secondary'],
    ['pending_approvals', 'fa-clipboard-check', 'primary'],
];
?>
<link href="<?php echo rateb_asset('css/platform-dashboard.css'); ?>" rel="stylesheet">

<div class="rateb-platform-dash-header mb-4">
    <div>
        <h2 class="h5 mb-1"><i class="fas fa-chart-line me-2 text-primary"></i><?php echo __('dashboard'); ?></h2>
        <p class="text-muted small mb-0"><?php echo __('platform_dashboard_intro'); ?></p>
    </div>
    <div class="text-muted small">
        <i class="fas fa-calendar-day me-1"></i><?php echo date('Y-m-d'); ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($widgets as $w) {
        if ($w[0] === 'pending_approvals' && (int) ($m[$w[0]] ?? 0) === 0) {
            continue;
        }
        $val = (int) ($m[$w[0]] ?? 0);
        ?>
    <div class="col-sm-6 col-xl-3">
        <div class="rateb-widget rateb-platform-widget">
            <div class="rateb-widget-icon bg-<?php echo $w[2]; ?> bg-opacity-10 text-<?php echo $w[2]; ?>">
                <i class="fas <?php echo $w[1]; ?>"></i>
            </div>
            <div class="rateb-widget-value"><?php echo $val; ?></div>
            <div class="rateb-widget-label"><?php echo __($w[0]); ?></div>
        </div>
    </div>
    <?php } ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="rateb-card rateb-chart-card h-100">
                    <div class="rateb-card-header"><?php echo __('company_growth'); ?></div>
                    <div class="rateb-card-body">
                        <canvas id="chart-companies" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($coValues); ?>'></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="rateb-card rateb-chart-card h-100">
                    <div class="rateb-card-header"><?php echo __('subscription_growth'); ?></div>
                    <div class="rateb-card-body">
                        <canvas id="chart-subscriptions" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($subLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subValues); ?>'></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="rateb-card rateb-chart-card h-100">
                    <div class="rateb-card-header"><?php echo __('user_growth'); ?></div>
                    <div class="rateb-card-body">
                        <canvas id="chart-users" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('user_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($userLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($userValues); ?>'></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="rateb-card rateb-chart-card h-100">
                    <div class="rateb-card-header"><?php echo __('company_status_distribution'); ?></div>
                    <div class="rateb-card-body">
                        <canvas id="chart-company-status" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_status_distribution')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($statusLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($statusValues); ?>'></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="rateb-card mb-3">
            <div class="rateb-card-header"><i class="fas fa-bell me-1"></i> <?php echo __('smart_alerts'); ?></div>
            <div class="rateb-card-body">
                <?php if ($alerts === []) { ?>
                <p class="text-muted small mb-0"><i class="fas fa-check-circle text-success me-1"></i><?php echo __('dashboard_no_alerts'); ?></p>
                <?php } else { foreach ($alerts as $alert) { ?>
                <a href="<?php echo Rateb\App\Core\View::escape((string) ($alert['url'] ?? '#')); ?>" class="rateb-platform-alert rateb-platform-alert-<?php echo Rateb\App\Core\View::escape((string) ($alert['severity'] ?? 'info')); ?>">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?php echo Rateb\App\Core\View::escape((string) ($alert['message'] ?? '')); ?></span>
                </a>
                <?php } } ?>
            </div>
        </div>
        <div class="rateb-card mb-3">
            <div class="rateb-card-header"><i class="fas fa-bolt me-1"></i> <?php echo __('quick_shortcuts'); ?></div>
            <div class="rateb-card-body">
                <div class="rateb-platform-shortcuts">
                    <a href="<?php echo rateb_url('admin/companies/create'); ?>" class="rateb-platform-shortcut"><i class="fas fa-building"></i><?php echo __('add_company'); ?></a>
                    <a href="<?php echo rateb_url('admin/users/create'); ?>" class="rateb-platform-shortcut"><i class="fas fa-user-plus"></i><?php echo __('add_user'); ?></a>
                    <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
                    <a href="<?php echo rateb_url('admin/accounting'); ?>" class="rateb-platform-shortcut"><i class="fas fa-calculator"></i><?php echo __('accounting_dashboard'); ?></a>
                    <?php } ?>
                    <?php if (rateb_nav_can('executive.dashboard.view')) { ?>
                    <a href="<?php echo rateb_url('admin/executive-dashboard'); ?>" class="rateb-platform-shortcut"><i class="fas fa-chart-pie"></i><?php echo __('executive_dashboard'); ?></a>
                    <?php } ?>
                    <?php if (rateb_nav_can('workflows.view')) { ?>
                    <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>" class="rateb-platform-shortcut"><i class="fas fa-clipboard-check"></i><?php echo __('approvals_oversight'); ?></a>
                    <?php } ?>
                    <a href="<?php echo rateb_url('admin/subscriptions'); ?>" class="rateb-platform-shortcut"><i class="fas fa-credit-card"></i><?php echo __('subscriptions'); ?></a>
                </div>
            </div>
        </div>
        <?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
        <div class="alert alert-info small mb-0">
            <i class="fas fa-calculator me-1"></i><?php echo __('dashboard_accounting_moved_hint'); ?>
        </div>
        <?php } ?>
    </div>
</div>

<div class="row g-3">
    <?php if ($recentCompanies !== []) { ?>
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-building me-1"></i> <?php echo __('recent_companies'); ?></span>
                <a href="<?php echo rateb_url('admin/companies'); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('view_all'); ?></a>
            </div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table mb-0">
                        <thead>
                        <tr>
                            <th><?php echo __('name'); ?></th>
                            <th><?php echo __('status'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentCompanies as $row) { ?>
                        <tr>
                            <td>
                                <a href="<?php echo rateb_url('admin/companies/' . (int) $row['id']); ?>" class="text-decoration-none">
                                    <?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?>
                                </a>
                            </td>
                            <td><span class="badge bg-secondary"><?php echo __((string) ($row['status'] ?? '')); ?></span></td>
                            <td class="text-muted small"><?php echo Rateb\App\Core\View::escape(substr((string) ($row['created_at'] ?? ''), 0, 10)); ?></td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
    <?php if ($topCompanies !== []) { ?>
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><i class="fas fa-trophy me-1"></i> <?php echo __('top_companies_by_po'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('workflow-list', [
                    'items' => $topCompanies,
                    'columns' => [
                        ['name' => 'company_name', 'label' => 'companies'],
                        ['name' => 'po_count', 'label' => 'purchase_orders'],
                        ['name' => 'total', 'label' => 'total', 'type' => 'money'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
    <?php } ?>
    <?php if ($recentLogins !== []) { ?>
    <div class="col-12">
        <div class="rateb-card">
            <div class="rateb-card-header"><i class="fas fa-right-to-bracket me-1"></i> <?php echo __('recent_logins'); ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table mb-0">
                        <thead>
                        <tr>
                            <th><?php echo __('email'); ?></th>
                            <th><?php echo __('user'); ?></th>
                            <th><?php echo __('status'); ?></th>
                            <th><?php echo __('date'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentLogins as $row) { ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['email'] ?? '')); ?></td>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['user_name'] ?? '—')); ?></td>
                            <td>
                                <?php if ((int) ($row['success'] ?? 0) === 1) { ?>
                                <span class="badge bg-success"><?php echo __('login_success'); ?></span>
                                <?php } else { ?>
                                <span class="badge bg-danger"><?php echo __('failed'); ?></span>
                                <?php } ?>
                            </td>
                            <td class="text-muted small"><?php echo Rateb\App\Core\View::escape((string) ($row['created_at'] ?? '')); ?></td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
