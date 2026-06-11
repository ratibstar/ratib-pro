<?php
$m = $metrics ?? [];
$c = $charts ?? [];
$revLabels = json_encode(array_column($c['monthly_revenue'] ?? [], 'month'));
$revValues = json_encode(array_map('floatval', array_column($c['monthly_revenue'] ?? [], 'total')));
$coLabels = json_encode(array_column($c['company_growth'] ?? [], 'month'));
$coValues = json_encode(array_map('intval', array_column($c['company_growth'] ?? [], 'total')));
$subLabels = json_encode(array_column($c['subscription_growth'] ?? [], 'month'));
$subValues = json_encode(array_map('intval', array_column($c['subscription_growth'] ?? [], 'total')));
?>
<div class="row g-3 mb-4">
    <?php
    $widgets = [
        ['total_companies', 'fa-building', 'primary', __('total_companies')],
        ['active_companies', 'fa-circle-check', 'success', __('active_companies')],
        ['revenue', 'fa-coins', 'warning', __('revenue')],
        ['subscriptions', 'fa-credit-card', 'info', __('subscriptions')],
        ['users', 'fa-users', 'secondary', __('users')],
        ['purchase_requests', 'fa-file-circle-plus', 'primary', __('purchase_requests')],
        ['purchase_orders', 'fa-file-invoice', 'success', __('purchase_orders')],
        ['inventory_value', 'fa-boxes-stacked', 'info', __('inventory_value')],
    ];
    foreach ($widgets as $w) {
        $val = $m[$w[0]] ?? 0;
        if ($w[0] === 'revenue' || $w[0] === 'inventory_value') {
            $val = number_format((float)$val, 2);
        }
        ?>
    <div class="col-sm-6 col-xl-3">
        <div class="rateb-widget">
            <div class="rateb-widget-icon bg-<?php echo $w[2]; ?> bg-opacity-10 text-<?php echo $w[2]; ?>">
                <i class="fas <?php echo $w[1]; ?>"></i>
            </div>
            <div class="rateb-widget-value"><?php echo Rateb\App\Core\View::escape($val); ?></div>
            <div class="rateb-widget-label"><?php echo Rateb\App\Core\View::escape($w[3]); ?></div>
        </div>
    </div>
    <?php } ?>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="rateb-card rateb-chart-card">
            <div class="rateb-card-header"><?php echo __('revenue'); ?></div>
            <div class="rateb-card-body">
                <canvas id="chart-revenue" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('revenue')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($revLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($revValues); ?>'></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rateb-card rateb-chart-card">
            <div class="rateb-card-header"><?php echo __('company_growth'); ?></div>
            <div class="rateb-card-body">
                <canvas id="chart-companies" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('company_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($coLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($coValues); ?>'></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rateb-card rateb-chart-card">
            <div class="rateb-card-header"><?php echo __('subscription_growth'); ?></div>
            <div class="rateb-card-body">
                <canvas id="chart-subscriptions" data-chart-label="<?php echo Rateb\App\Core\View::escape(__('subscription_growth')); ?>" data-labels='<?php echo Rateb\App\Core\View::escape($subLabels); ?>' data-values='<?php echo Rateb\App\Core\View::escape($subValues); ?>'></canvas>
            </div>
        </div>
    </div>
</div>
