<?php
declare(strict_types=1);
?>
<div class="rateb-pos__modal" data-pos-line-discount-modal hidden role="dialog" aria-modal="true" aria-labelledby="rateb-pos-line-discount-title">
    <div class="rateb-pos__modal-backdrop" data-pos-line-discount-close tabindex="-1" aria-hidden="true"></div>
    <div class="rateb-pos__modal-panel rateb-pos__modal-panel--sm">
        <header class="rateb-pos__modal-head">
            <h2 id="rateb-pos-line-discount-title"><?php echo __('pos_line_discount'); ?></h2>
            <button type="button" class="rateb-pos__modal-close" data-pos-line-discount-close aria-label="<?php echo __('close'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="rateb-pos__modal-body">
            <p class="rateb-pos__hint" data-pos-line-discount-target></p>
            <label class="rateb-pos__field-label" for="rateb-pos-line-disc-type"><?php echo __('pos_invoice_discount'); ?></label>
            <div class="rateb-pos__pay-disc-row">
                <select id="rateb-pos-line-disc-type" class="rateb-pos__input rateb-pos__input--sm" data-pos-line-discount-type>
                    <option value="amount"><?php echo __('pos_discount_amount'); ?></option>
                    <option value="percent"><?php echo __('pos_discount_percent'); ?></option>
                </select>
                <input type="number" id="rateb-pos-line-disc-value" class="rateb-pos__input rateb-pos__input--sm" data-pos-line-discount-value value="0" min="0" step="0.01" inputmode="decimal" />
            </div>
            <button type="button" class="rateb-pos__charge rateb-pos__charge--sm" data-pos-line-discount-apply><?php echo __('pos_apply_line_discount'); ?></button>
        </div>
    </div>
</div>

<div class="rateb-pos__modal" data-pos-cashier-tools-modal hidden role="dialog" aria-modal="true" aria-labelledby="rateb-pos-cashier-tools-title">
    <div class="rateb-pos__modal-backdrop" data-pos-cashier-tools-close tabindex="-1" aria-hidden="true"></div>
    <div class="rateb-pos__modal-panel rateb-pos__modal-panel--sm">
        <header class="rateb-pos__modal-head">
            <h2 id="rateb-pos-cashier-tools-title"><?php echo __('pos_cashier_tools'); ?></h2>
            <button type="button" class="rateb-pos__modal-close" data-pos-cashier-tools-close aria-label="<?php echo __('close'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="rateb-pos__modal-body rateb-pos__cashier-tools">
            <button type="button" class="rateb-pos__util-btn rateb-pos__util-btn--accent" data-pos-drawer-open><?php echo __('pos_open_drawer'); ?></button>
            <form class="rateb-pos__cashier-form" data-pos-drawer-event-form>
                <label class="rateb-pos__field-label" for="rateb-pos-drawer-event-type"><?php echo __('pos_drawer_event'); ?></label>
                <select id="rateb-pos-drawer-event-type" class="rateb-pos__input rateb-pos__input--block" data-pos-drawer-event-type>
                    <option value="pay_in"><?php echo __('pos_pay_in'); ?></option>
                    <option value="pay_out"><?php echo __('pos_pay_out'); ?></option>
                    <option value="no_sale"><?php echo __('pos_no_sale'); ?></option>
                </select>
                <label class="rateb-pos__field-label" for="rateb-pos-drawer-event-amount"><?php echo __('pos_payment_amount'); ?></label>
                <input type="number" id="rateb-pos-drawer-event-amount" class="rateb-pos__input rateb-pos__input--block" data-pos-drawer-event-amount value="0" min="0" step="0.01" inputmode="decimal" />
                <label class="rateb-pos__field-label" for="rateb-pos-drawer-event-notes"><?php echo __('notes'); ?></label>
                <input type="text" id="rateb-pos-drawer-event-notes" class="rateb-pos__input rateb-pos__input--block" data-pos-drawer-event-notes maxlength="200" />
                <button type="submit" class="rateb-pos__charge rateb-pos__charge--sm"><?php echo __('save'); ?></button>
            </form>
            <a class="rateb-pos__cashier-link" data-pos-shift-close-link href="#" hidden><?php echo __('pos_shift_close'); ?></a>
        </div>
    </div>
</div>

<div class="rateb-pos__modal" data-pos-x-report-modal hidden role="dialog" aria-modal="true" aria-labelledby="rateb-pos-x-report-title">
    <div class="rateb-pos__modal-backdrop" data-pos-x-report-close tabindex="-1" aria-hidden="true"></div>
    <div class="rateb-pos__modal-panel">
        <header class="rateb-pos__modal-head">
            <h2 id="rateb-pos-x-report-title"><?php echo __('pos_x_report'); ?></h2>
            <button type="button" class="rateb-pos__modal-close" data-pos-x-report-close aria-label="<?php echo __('close'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="rateb-pos__modal-body" data-pos-x-report-body>
            <p class="rateb-pos__hint"><?php echo __('pos_x_report_hint'); ?></p>
        </div>
    </div>
</div>

<div class="rateb-pos__modal" data-pos-shortcuts-modal hidden role="dialog" aria-modal="true" aria-labelledby="rateb-pos-shortcuts-title">
    <div class="rateb-pos__modal-backdrop" data-pos-shortcuts-close tabindex="-1" aria-hidden="true"></div>
    <div class="rateb-pos__modal-panel rateb-pos__modal-panel--sm">
        <header class="rateb-pos__modal-head">
            <h2 id="rateb-pos-shortcuts-title"><?php echo __('pos_keyboard_shortcuts'); ?></h2>
            <button type="button" class="rateb-pos__modal-close" data-pos-shortcuts-close aria-label="<?php echo __('close'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="rateb-pos__modal-body">
            <ul class="rateb-pos__shortcuts-visible" data-pos-shortcuts-visible></ul>
        </div>
    </div>
</div>
