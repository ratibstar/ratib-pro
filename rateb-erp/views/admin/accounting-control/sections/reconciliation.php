<?php include RATEB_VIEWS_PATH . '/admin/accounting-control/sections/_section-shell.php'; ?>
<div class="acc-section" data-acc-page="reconciliation">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-primary acc-run-reconcile"><?php echo __('accounting_control_run_reconciliation'); ?></button>
    </div>
    <h6 class="small text-muted"><?php echo __('accounting_control_correction_history'); ?></h6>
    <div class="table-responsive mb-3">
        <table class="table table-sm acc-correction-timeline"><thead><tr>
            <th><?php echo __('accounting_control_col_status'); ?></th>
            <th><?php echo __('accounting_control_col_created'); ?></th>
            <th><?php echo __('accounting_control_workflow_executed'); ?></th>
        </tr></thead><tbody></tbody></table>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover acc-recon-table"><thead><tr>
            <th><?php echo __('accounting_control_col_id'); ?></th>
            <th><?php echo __('accounting_control_col_risk'); ?></th>
            <th><?php echo __('accounting_control_col_period'); ?></th>
            <th><?php echo __('accounting_control_col_drift_items'); ?></th>
            <th><?php echo __('accounting_control_col_corrections'); ?></th>
            <th><?php echo __('accounting_control_col_actions'); ?></th>
        </tr></thead><tbody></tbody></table>
    </div>
</div>
