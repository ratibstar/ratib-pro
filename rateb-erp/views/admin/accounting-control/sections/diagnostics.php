<?php include RATEB_VIEWS_PATH . '/admin/accounting-control/sections/_section-shell.php'; ?>
<div class="acc-section" data-acc-page="diagnostics">
    <div class="alert acc-diag-overall mb-3" role="status"></div>
    <div class="table-responsive">
        <table class="table table-sm acc-diagnostics-table">
            <thead><tr>
                <th><?php echo __('accounting_control_col_id'); ?></th>
                <th><?php echo __('accounting_control_col_status'); ?></th>
                <th><?php echo __('accounting_control_col_summary'); ?></th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
