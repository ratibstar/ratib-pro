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
    <div class="table-responsive">
        <table class="table table-sm acc-consolidation-table"><thead><tr>
            <th><?php echo __('accounting_control_col_run'); ?></th>
            <th><?php echo __('accounting_control_col_company'); ?></th>
            <th><?php echo __('accounting_control_col_period'); ?></th>
            <th><?php echo __('accounting_control_col_payload'); ?></th>
            <th></th>
        </tr></thead><tbody></tbody></table>
    </div>
</div>
