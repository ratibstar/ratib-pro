<div class="acc-section" data-acc-page="audit">
    <div class="row g-2 mb-2">
        <div class="col-md-3"><input type="text" class="form-control form-control-sm acc-filter-action" placeholder="<?php echo __('accounting_control_col_action'); ?>"></div>
        <div class="col-md-3"><input type="text" class="form-control form-control-sm acc-filter-uuid" placeholder="<?php echo __('accounting_control_col_uuid'); ?>"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover acc-data-table">
            <thead><tr>
                <th><?php echo __('accounting_control_col_time'); ?></th>
                <th><?php echo __('accounting_control_col_uuid'); ?></th>
                <th><?php echo __('accounting_control_col_action'); ?></th>
                <th><?php echo __('accounting_control_col_system'); ?></th>
                <th><?php echo __('accounting_control_col_status'); ?></th>
                <th></th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
    <h6 class="mt-4"><?php echo __('accounting_control_evidence_packs'); ?></h6>
    <div class="table-responsive">
        <table class="table table-sm acc-evidence-table">
            <thead><tr>
                <th><?php echo __('accounting_control_col_id'); ?></th>
                <th><?php echo __('accounting_control_col_period'); ?></th>
                <th><?php echo __('accounting_control_col_hash'); ?></th>
                <th><?php echo __('accounting_control_col_created'); ?></th>
                <th></th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
