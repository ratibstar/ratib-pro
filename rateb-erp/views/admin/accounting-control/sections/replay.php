<?php include RATEB_VIEWS_PATH . '/admin/accounting-control/sections/_section-shell.php'; ?>
<div class="acc-section" data-acc-page="replay">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-outline-primary acc-replay-preview" data-mode="single"><?php echo __('accounting_control_replay_preview_single'); ?></button>
        <button type="button" class="btn btn-sm btn-outline-warning acc-replay-run" data-mode="single"><?php echo __('accounting_control_replay_single'); ?></button>
        <button type="button" class="btn btn-sm btn-outline-primary acc-replay-preview" data-mode="failed"><?php echo __('accounting_control_replay_preview_failed'); ?></button>
        <button type="button" class="btn btn-sm btn-outline-warning acc-replay-run" data-mode="failed"><?php echo __('accounting_control_replay_failed_btn'); ?></button>
        <button type="button" class="btn btn-sm btn-outline-secondary acc-replay-preview" data-mode="period"><?php echo __('accounting_control_replay_preview_period'); ?></button>
        <button type="button" class="btn btn-sm btn-warning acc-replay-run" data-mode="period"><?php echo __('accounting_control_replay_period'); ?></button>
        <button type="button" class="btn btn-sm btn-outline-secondary acc-replay-preview" data-mode="company"><?php echo __('accounting_control_replay_preview_company'); ?></button>
        <button type="button" class="btn btn-sm btn-warning acc-replay-run" data-mode="company"><?php echo __('accounting_control_replay_company'); ?></button>
        <button type="button" class="btn btn-sm btn-outline-secondary acc-replay-preview" data-mode="branch"><?php echo __('accounting_control_replay_preview_branch'); ?></button>
        <button type="button" class="btn btn-sm btn-warning acc-replay-run" data-mode="branch"><?php echo __('accounting_control_replay_branch'); ?></button>
    </div>
    <div class="acc-replay-progress d-none mb-3" role="status" aria-live="polite">
        <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>
    </div>
    <h6><?php echo __('accounting_control_replay_summary'); ?></h6>
    <pre class="acc-replay-result bg-body-secondary p-3 rounded small"></pre>
    <h6 class="mt-3"><?php echo __('accounting_control_replay_queue'); ?></h6>
    <div class="table-responsive mb-3">
        <table class="table table-sm acc-replay-queue"><thead><tr>
            <th><?php echo __('accounting_control_col_uuid'); ?></th>
            <th><?php echo __('accounting_control_col_status'); ?></th>
            <th><?php echo __('accounting_control_col_created'); ?></th>
        </tr></thead><tbody></tbody></table>
    </div>
    <h6><?php echo __('accounting_control_replay_history'); ?></h6>
    <div class="table-responsive">
        <table class="table table-sm acc-replay-history"><thead><tr>
            <th><?php echo __('accounting_control_col_time'); ?></th>
            <th><?php echo __('accounting_control_col_uuid'); ?></th>
            <th><?php echo __('accounting_control_col_action'); ?></th>
            <th><?php echo __('accounting_control_col_status'); ?></th>
        </tr></thead><tbody></tbody></table>
    </div>
</div>
