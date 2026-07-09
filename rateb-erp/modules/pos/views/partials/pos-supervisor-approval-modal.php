<?php
declare(strict_types=1);
?>
<div class="rateb-pos__modal rateb-pos__supervisor-modal" data-pos-supervisor-modal hidden role="dialog" aria-modal="true" aria-labelledby="rateb-pos-supervisor-title">
    <div class="rateb-pos__modal-backdrop" data-pos-supervisor-close tabindex="-1" aria-hidden="true"></div>
    <div class="rateb-pos__modal-panel">
        <header class="rateb-pos__modal-head">
            <h2 id="rateb-pos-supervisor-title"><?php echo __('pos_supervisor_approval'); ?></h2>
            <button type="button" class="rateb-pos__modal-close" data-pos-supervisor-close aria-label="<?php echo __('close'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="rateb-pos__modal-body">
            <div class="rateb-pos__supervisor-scan">
                <div class="rateb-pos__supervisor-scan-icon" aria-hidden="true">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 11c1.66 0 3-1.34 3-3S13.66 5 12 5 9 6.34 9 8s1.34 3 3 3z"/><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><path d="M7 11V8a5 5 0 0 1 10 0v3"/></svg>
                </div>
                <p data-pos-supervisor-message><?php echo __('pos_supervisor_scan_prompt'); ?></p>
                <p class="rateb-pos__hint" data-pos-supervisor-action-label hidden></p>
                <button type="button" class="rateb-pos__charge rateb-pos__charge--sm" data-pos-supervisor-scan>
                    <?php echo __('pos_supervisor_scan_fingerprint'); ?>
                </button>
                <p class="rateb-pos__hint rateb-pos__toast is-error" data-pos-supervisor-error hidden></p>
            </div>
        </div>
    </div>
</div>
