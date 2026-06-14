<?php
$report = $report ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<form method="get" class="rateb-card mb-3">
    <div class="rateb-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?php echo __('date_from'); ?></label>
                <input type="date" name="from" class="form-control" value="<?php echo Rateb\App\Core\View::escape($from ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('date_to'); ?></label>
                <input type="date" name="to" class="form-control" value="<?php echo Rateb\App\Core\View::escape($to ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><?php echo __('filter'); ?></button>
            </div>
        </div>
    </div>
</form>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('output_vat'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($report['output_vat'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('input_vat'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($report['input_vat'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('net_vat'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($report['net_vat'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('invoice_tax_total'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($report['invoice_tax'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
</div>
<p class="text-muted small"><?php echo __('vat_report_help'); ?></p>
