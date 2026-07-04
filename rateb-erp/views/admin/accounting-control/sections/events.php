<div class="acc-section" data-acc-page="events">
    <div class="row g-2 mb-2 acc-extra-filters">
        <div class="col-md-2"><input type="text" class="form-control form-control-sm acc-filter-uuid" placeholder="<?php echo __('accounting_control_col_uuid'); ?>"></div>
        <div class="col-md-2">
            <select class="form-select form-select-sm acc-filter-status">
                <option value=""><?php echo __('accounting_control_col_status'); ?></option>
                <option value="pending"><?php echo __('accounting_control_status_pending'); ?></option>
                <option value="processed"><?php echo __('accounting_control_status_processed'); ?></option>
                <option value="failed"><?php echo __('accounting_control_status_failed'); ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm acc-filter-system">
                <option value=""><?php echo __('accounting_control_col_system'); ?></option>
                <option value="rateb-erp">rateb-erp</option>
                <option value="main-site">main-site</option>
                <option value="control-panel">control-panel</option>
                <option value="ledger">ledger</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover acc-data-table">
            <thead><tr>
                <th><?php echo __('accounting_control_col_uuid'); ?></th>
                <th><?php echo __('accounting_control_col_system'); ?></th>
                <th><?php echo __('accounting_control_col_type'); ?></th>
                <th><?php echo __('accounting_control_col_status'); ?></th>
                <th><?php echo __('accounting_control_col_company'); ?></th>
                <th><?php echo __('accounting_control_col_branch'); ?></th>
                <th><?php echo __('accounting_control_col_created'); ?></th>
                <th></th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
    <nav class="acc-pagination"></nav>
</div>
