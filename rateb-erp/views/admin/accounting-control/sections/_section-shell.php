<?php
/** Shared Phase 7 section chrome — KPI row, loading, empty, last-updated. */
?>
<div class="acc-section-meta d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
    <div class="acc-section-status"></div>
    <div class="acc-last-updated text-muted small"></div>
</div>
<div class="acc-loading d-none text-center py-4" role="status" aria-live="polite">
    <div class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></div>
    <span class="ms-2"><?php echo __('accounting_control_loading'); ?></span>
</div>
<div class="acc-empty d-none alert alert-light text-center"><?php echo __('accounting_control_empty'); ?></div>
<div class="row g-2 acc-section-kpis mb-3"></div>
