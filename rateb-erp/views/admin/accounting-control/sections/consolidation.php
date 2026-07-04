<?php include RATEB_VIEWS_PATH . '/admin/accounting-control/sections/_section-shell.php'; ?>
<div class="acc-section" data-acc-page="consolidation">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <select class="form-select form-select-sm w-auto acc-consolidation-type">
            <option value="trial_balance"><?php echo __('accounting_control_consolidated_tb'); ?></option>
            <option value="balance_sheet"><?php echo __('accounting_control_consolidated_bs'); ?></option>
            <option value="profit_loss"><?php echo __('accounting_control_consolidated_pl'); ?></option>
        </select>
        <button type="button" class="btn btn-sm btn-primary acc-load-consolidation"><?php echo __('accounting_control_btn_load'); ?></button>
        <button type="button" class="btn btn-sm btn-warning acc-run-consolidation"><?php echo __('accounting_control_run_consolidation'); ?></button>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="acc-hierarchy-tree small"></div></div>
        <div class="col-md-8">
            <h6 class="small text-muted"><?php echo __('accounting_control_eliminations'); ?></h6>
            <ul class="acc-eliminations-list list-group list-group-flush small mb-2"></ul>
        </div>
    </div>
    <h6 class="small text-muted"><?php echo __('accounting_control_execution_history'); ?></h6>
    <div class="table-responsive mb-3">
        <table class="table table-sm acc-execution-history"><thead><tr>
            <th><?php echo __('accounting_control_col_run'); ?></th>
            <th><?php echo __('accounting_control_col_period'); ?></th>
            <th><?php echo __('records'); ?></th>
            <th><?php echo __('accounting_control_col_created'); ?></th>
        </tr></thead><tbody></tbody></table>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover acc-consolidation-table"><thead><tr>
            <th><?php echo __('accounting_control_col_run'); ?></th>
            <th><?php echo __('accounting_control_col_account'); ?></th>
            <th><?php echo __('accounting_control_col_name'); ?></th>
            <th><?php echo __('accounting_control_col_debit'); ?></th>
            <th><?php echo __('accounting_control_col_credit'); ?></th>
            <th></th>
        </tr></thead><tbody></tbody></table>
    </div>
</div>
