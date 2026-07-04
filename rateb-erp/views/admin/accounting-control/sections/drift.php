<?php include RATEB_VIEWS_PATH . '/admin/accounting-control/sections/_section-shell.php'; ?>
<div class="acc-section" data-acc-page="drift">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-primary acc-run-drift"><?php echo __('accounting_control_run_detection'); ?></button>
        <select class="form-select form-select-sm w-auto acc-filter-severity">
            <option value=""><?php echo __('accounting_control_all_severity'); ?></option>
            <option value="high"><?php echo __('accounting_control_severity_high'); ?></option>
            <option value="medium"><?php echo __('accounting_control_severity_medium'); ?></option>
            <option value="low"><?php echo __('accounting_control_severity_low'); ?></option>
        </select>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-6"><canvas id="acc-chart-drift-severity" height="120"></canvas></div>
        <div class="col-md-6"><canvas id="acc-chart-drift-detail" height="120"></canvas></div>
    </div>
    <h6 class="small text-muted"><?php echo __('accounting_control_recommended_actions'); ?></h6>
    <ul class="acc-drift-actions list-group list-group-flush small mb-3"></ul>
    <div class="table-responsive">
        <table class="table table-sm table-hover acc-drift-table"><thead><tr>
            <th><?php echo __('accounting_control_col_id'); ?></th>
            <th><?php echo __('accounting_control_col_period'); ?></th>
            <th><?php echo __('accounting_control_col_severity'); ?></th>
            <th><?php echo __('accounting_control_col_summary'); ?></th>
            <th></th>
        </tr></thead><tbody></tbody></table>
    </div>
</div>
