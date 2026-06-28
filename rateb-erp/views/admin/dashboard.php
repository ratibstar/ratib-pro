<?php
$m = $metrics ?? [];
$c = $charts ?? [];
$coLabels = json_encode(array_column($c['company_growth'] ?? [], 'month'));
$coValues = json_encode(array_map('intval', array_column($c['company_growth'] ?? [], 'total')));
$subLabels = json_encode(array_column($c['subscription_growth'] ?? [], 'month'));
$subValues = json_encode(array_map('intval', array_column($c['subscription_growth'] ?? [], 'total')));
?>
<div class="row g-3 mb-4">
    <?php
    $widgets = [
        ['total_companies', 'fa-building', 'primary', __('total_companies'), false],
        ['active_companies', 'fa-circle-check', 'success', __('active_companies'), false],
        ['subscriptions', 'fa-credit-card', 'info', __('subscriptions'), false],
        ['users', 'fa-users', 'secondary', __('users'), false],
    ];
    foreach ($widgets as $w) {
        $val = $m[$w[0]] ?? 0;
        ?>
    <div class="col-sm-6 col-xl-3">
        <div class="rateb-widget">
            <div class="rateb-widget-icon bg-<?php echo $w[2]; ?> bg-opacity-10 text-<?php echo $w[2]; ?>">
                <i class="fas <?php echo $w[1]; ?>"></i>
            </div>
            <div class="rateb-widget-value"><?php echo Rateb\App\Core\View::escape((string) $val); ?></div>
            <div class="rateb-widget-label"><?php echo Rateb\App\Core\View::escape($w[3]); ?></div>
        </div>
    </div>
    <?php } ?>
</div>
<?php if (rateb_nav_can('accounting.view', 'accounting')) { ?>
<div class="alert alert-info d-flex flex-wrap align-items-center gap-2 mb-4">
    <i class="fas fa-calculator"></i>
    <span class="small"><?php echo __('dashboard_accounting_moved_hint'); ?></span>
    <a href="<?php echo rateb_is_super_admin() ? rateb_url('admin/accounting') : rateb_app_url('accounting'); ?>" class="btn btn-sm btn-outline-primary ms-auto">
        <?php echo __('accounting_dashboard'); ?>
    </a>
</div>
<?php } ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="rateb-card rateb-chart-card">
            <div class="rateb-card-header"><?php echo __('company_growth'); ?></div>
            <div class="rateb-card-body">
                <canvas id="chart-companies" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($coValues); ?>'></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card rateb-chart-card">
            <div class="rateb-card-header"><?php echo __('subscription_growth'); ?></div>
            <div class="rateb-card-body">
                <canvas id="chart-subscriptions" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($subLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subValues); ?>'></canvas>
            </div>
        </div>
    </div>
</div>
