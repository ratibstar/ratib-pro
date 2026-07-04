<?php
/** @var string $accSection */
include RATEB_VIEWS_PATH . '/admin/accounting-control/sections/_section-shell.php';
?>
<div class="acc-section" data-acc-page="projections">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <select class="form-select form-select-sm w-auto acc-projection-type">
            <option value="trial_balance"><?php echo __('accounting_control_projection_trial_balance'); ?></option>
            <option value="balance_sheet"><?php echo __('accounting_control_projection_balance_sheet'); ?></option>
            <option value="profit_loss"><?php echo __('accounting_control_projection_profit_loss'); ?></option>
            <option value="cashflow"><?php echo __('accounting_control_projection_cashflow'); ?></option>
        </select>
        <button type="button" class="btn btn-sm btn-primary acc-load-projection"><?php echo __('accounting_control_btn_load'); ?></button>
        <button type="button" class="btn btn-sm btn-warning acc-rebuild-snapshot"><?php echo __('accounting_control_rebuild_snapshot'); ?></button>
    </div>
    <div class="alert alert-secondary acc-period-closure small"></div>
    <h6 class="small text-muted"><?php echo __('accounting_control_snapshot_history'); ?></h6>
    <ul class="acc-snapshot-history list-group list-group-flush small mb-3"></ul>
    <div class="table-responsive">
        <table class="table table-sm table-hover acc-projection-table"><thead><tr>
            <th><?php echo __('accounting_control_col_account'); ?></th>
            <th><?php echo __('accounting_control_col_name'); ?></th>
            <th><?php echo __('accounting_control_col_debit'); ?></th>
            <th><?php echo __('accounting_control_col_credit'); ?></th>
            <th><?php echo __('accounting_control_col_amount'); ?></th>
            <th></th>
        </tr></thead><tbody></tbody></table>
    </div>
</div>
