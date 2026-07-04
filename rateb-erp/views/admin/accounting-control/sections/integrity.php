<?php include RATEB_VIEWS_PATH . '/admin/accounting-control/sections/_section-shell.php'; ?>
<div class="acc-section" data-acc-page="integrity">
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="acc-card acc-integrity-score card p-3 text-center"><div class="display-4">—</div><div><?php echo __('accounting_control_integrity_score'); ?></div></div></div>
        <div class="col-md-9">
            <h6><?php echo __('accounting_control_golden_ledger'); ?></h6>
            <pre class="acc-golden-summary small bg-body-secondary p-2 rounded"></pre>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <h6><?php echo __('accounting_control_period_locks'); ?></h6>
            <div class="table-responsive">
                <table class="table table-sm acc-integrity-locks"><thead><tr>
                    <th><?php echo __('accounting_control_col_period'); ?></th>
                    <th><?php echo __('accounting_control_col_status'); ?></th>
                    <th><?php echo __('accounting_control_col_closed'); ?></th>
                </tr></thead><tbody></tbody></table>
            </div>
        </div>
        <div class="col-md-6">
            <h6><?php echo __('accounting_control_hash_verification'); ?></h6>
            <pre class="acc-hash-verification small bg-body-secondary p-2 rounded"></pre>
        </div>
    </div>
    <h6 class="mt-3"><?php echo __('accounting_control_conflict_timeline'); ?></h6>
    <div class="table-responsive">
        <table class="table table-sm acc-conflict-timeline"><thead><tr>
            <th><?php echo __('accounting_control_col_type'); ?></th>
            <th><?php echo __('accounting_control_col_summary'); ?></th>
        </tr></thead><tbody></tbody></table>
    </div>
    <h6 class="mt-3"><?php echo __('accounting_control_correction_history'); ?></h6>
    <div class="table-responsive">
        <table class="table table-sm acc-correction-history"><thead><tr>
            <th><?php echo __('accounting_control_col_status'); ?></th>
            <th><?php echo __('accounting_control_col_created'); ?></th>
            <th></th>
        </tr></thead><tbody></tbody></table>
    </div>
    <h6 class="mt-3"><?php echo __('accounting_control_certification_packs'); ?></h6>
    <div class="table-responsive">
        <table class="table table-sm acc-evidence-table"><thead><tr>
            <th><?php echo __('accounting_control_col_id'); ?></th>
            <th><?php echo __('accounting_control_col_period'); ?></th>
            <th><?php echo __('accounting_control_col_hash'); ?></th>
            <th><?php echo __('accounting_control_col_created'); ?></th>
            <th></th>
        </tr></thead><tbody></tbody></table>
    </div>
</div>
