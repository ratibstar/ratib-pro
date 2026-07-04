<div class="acc-section" data-acc-page="integrity">
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="acc-card acc-integrity-score card p-3 text-center"><div class="display-4">—</div><div><?php echo __('accounting_control_integrity_score'); ?></div></div></div>
        <div class="col-md-9"><pre class="acc-golden-summary small bg-dark text-light p-2 rounded"></pre></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm acc-integrity-locks"><thead><tr>
            <th><?php echo __('accounting_control_col_period'); ?></th>
            <th><?php echo __('accounting_control_col_status'); ?></th>
            <th><?php echo __('accounting_control_col_closed'); ?></th>
        </tr></thead><tbody></tbody></table>
    </div>
    <h6 class="mt-3"><?php echo __('accounting_control_evidence_packs'); ?></h6>
    <div class="table-responsive">
        <table class="table table-sm acc-evidence-table"><thead><tr>
            <th><?php echo __('accounting_control_col_hash'); ?></th>
            <th><?php echo __('accounting_control_col_period'); ?></th>
            <th></th>
        </tr></thead><tbody></tbody></table>
    </div>
</div>
