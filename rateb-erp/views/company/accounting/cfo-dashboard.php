<?php
$m = $metrics ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('cash_position'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($m['cash_position'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('revenue_ytd'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($m['revenue_ytd'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('net_margin'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($m['net_margin'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('ar_open'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($m['ar_open'] ?? 0), 2); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('ap_open'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($m['ap_open'] ?? 0), 2); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('dso_days'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($m['dso_days'] ?? 0), 1); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('dpo_days'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($m['dpo_days'] ?? 0), 1); ?></div>
        </div>
    </div>
</div>
<p class="text-muted small"><?php echo __('cfo_dashboard_help'); ?></p>
