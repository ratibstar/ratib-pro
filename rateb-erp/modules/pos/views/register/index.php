<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var string|null $csrf */
$registerReady = (bool) ($context['register_ready'] ?? false);
$canReturns = (bool) ($context['can_returns'] ?? false);
$shift = $context['shift'] ?? null;
$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
$csrfToken = $csrf ?? \Rateb\App\Core\Csrf::token();
?>
<?php $csrf = $csrfToken; ?>
<script type="application/json" id="rateb-pos-bootstrap"><?php include __DIR__ . '/../partials/pos-register-bootstrap.php'; ?></script>

<div class="rateb-pos<?php echo $registerReady ? '' : ' rateb-pos--locked'; ?>"
     data-pos-register
     data-register-ready="<?php echo $registerReady ? '1' : '0'; ?>"
     aria-live="polite">

    <?php if (!$registerReady): ?>
    <div class="rateb-pos__shift-gate" role="dialog" aria-modal="true" aria-labelledby="rateb-pos-shift-title">
        <div class="rateb-pos__shift-gate-card">
            <h1 id="rateb-pos-shift-title" class="rateb-pos__shift-gate-title"><?php echo __('pos_shift_not_open'); ?></h1>
            <p class="rateb-pos__shift-gate-text"><?php echo __('pos_no_shift_warning'); ?></p>
            <form class="rateb-pos__shift-form" method="post" action="<?php echo rateb_app_url('pos/shifts/open'); ?>" data-pos-shift-form>
                <input type="hidden" name="_csrf" value="<?php echo \Rateb\App\Pos\Support\PosView::escape($csrfToken); ?>" />
                <label class="rateb-pos__field-label" for="rateb-pos-gate-terminal"><?php echo __('pos_terminals'); ?></label>
                <select class="rateb-pos__input rateb-pos__input--block" id="rateb-pos-gate-terminal" name="terminal_id" required data-pos-shift-terminal>
                    <option value=""><?php echo __('select'); ?></option>
                </select>
                <label class="rateb-pos__field-label" for="rateb-pos-gate-float"><?php echo __('pos_opening_float'); ?></label>
                <input class="rateb-pos__input rateb-pos__input--block" type="number" step="0.01" min="0" id="rateb-pos-gate-float" name="opening_float" value="0" inputmode="decimal" data-pos-shift-float />
                <button type="submit" class="rateb-pos__charge rateb-pos__charge--gate" data-pos-shift-open><?php echo __('pos_shift_open'); ?></button>
            </form>
            <a class="rateb-pos__shift-continue" href="<?php echo rateb_app_url('pos/register'); ?>"><?php echo __('pos_register'); ?></a>
        </div>
    </div>
    <?php endif; ?>

    <?php include __DIR__ . '/../partials/pos-register-header.php'; ?>

    <?php
    $sessionSnap = is_array($session ?? null) ? $session : [];
    $orderRef = str_pad((string) ((int) ($sessionSnap['db_session_id'] ?? 0)), 6, '0', STR_PAD_LEFT);
    ?>

    <div class="rateb-pos__stage">
        <?php include __DIR__ . '/../partials/pos-register-nav.php'; ?>

        <main class="rateb-pos__catalog" aria-label="<?php echo __('pos_products'); ?>">
            </div>
        </main>

        <aside class="rateb-pos__ticket" aria-label="<?php echo __('pos_cart'); ?>">
            <header class="rateb-pos__order-bar">
                <span class="rateb-pos__order-no">#<?php echo \Rateb\App\Pos\Support\PosView::escape($orderRef); ?></span>
                <div class="rateb-pos__order-actions">
                    <button type="button" class="rateb-pos__order-btn rateb-pos__order-btn--cancel" data-pos-clear-cart data-pos-cap-cancel><?php echo __('pos_cancel_order'); ?></button>
                    <button type="button" class="rateb-pos__order-btn rateb-pos__order-btn--quote" data-pos-save-quote><?php echo __('pos_save_quote'); ?></button>
                    <button type="button" class="rateb-pos__order-btn rateb-pos__order-btn--hold" data-pos-suspend><?php echo __('pos_suspend_sale'); ?></button>
                    <button type="button" class="rateb-pos__order-btn rateb-pos__order-btn--new" data-pos-new-sale><?php echo __('pos_new_sale'); ?></button>
                </div>
            </header>

            <section class="rateb-pos__saved" aria-label="<?php echo __('pos_saved_orders'); ?>">
                <div class="rateb-pos__saved-head">
                    <div class="rateb-pos__saved-tabs" role="tablist">
                        <button type="button" class="rateb-pos__saved-tab is-active" role="tab" aria-selected="true" data-pos-saved-tab="suspended"><?php echo __('pos_suspended_sales'); ?></button>
                        <button type="button" class="rateb-pos__saved-tab" role="tab" aria-selected="false" data-pos-saved-tab="quotes"><?php echo __('pos_quotes'); ?></button>
                    </div>
                    <button type="button" class="rateb-pos__saved-refresh" data-pos-saved-refresh aria-label="<?php echo __('refresh'); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                    </button>
                </div>
                <div class="rateb-pos__saved-panel is-active" data-pos-saved-panel="suspended" role="tabpanel">
                    <p class="rateb-pos__saved-empty" data-pos-suspended-empty hidden><?php echo __('pos_suspended_empty'); ?></p>
                    <div class="rateb-pos__saved-list" data-pos-suspended-list></div>
                </div>
                <div class="rateb-pos__saved-panel" data-pos-saved-panel="quotes" role="tabpanel" hidden>
                    <p class="rateb-pos__saved-empty" data-pos-quotes-empty hidden><?php echo __('pos_quotes_empty'); ?></p>
                    <div class="rateb-pos__saved-list" data-pos-quotes-list></div>
                </div>
            </section>

            <button type="button" class="rateb-pos__ticket-customer" data-pos-focus-customer aria-label="<?php echo __('pos_customer'); ?>">
                <svg class="rateb-pos__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span data-pos-toolbar-customer><?php echo __('pos_walk_in_customer'); ?></span>
                <svg class="rateb-pos__icon rateb-pos__ticket-customer-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <div class="rateb-pos__ticket-body">
                <div class="rateb-pos__ticket-lines" data-pos-cart-lines role="list"></div>
                <div class="rateb-pos__ticket-empty" data-pos-cart-empty>
                    <svg class="rateb-pos__ticket-empty-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <p><?php echo __('pos_cart_empty'); ?></p>
                </div>
            </div>

            <footer class="rateb-pos__ticket-foot">
                <dl class="rateb-pos__totals">
                    <div class="rateb-pos__totals-row rateb-pos__totals-row--muted">
                        <dt><?php echo __('pos_subtotal'); ?></dt>
                        <dd data-pos-subtotal>0.00</dd>
                    </div>
                    <div class="rateb-pos__totals-row rateb-pos__totals-row--muted" data-pos-totals-discount-wrap hidden>
                        <dt><?php echo __('pos_discount_total'); ?></dt>
                        <dd data-pos-discount-total>0.00</dd>
                    </div>
                    <div class="rateb-pos__totals-row rateb-pos__totals-row--muted">
                        <dt><?php echo __('pos_tax'); ?></dt>
                        <dd data-pos-tax>0.00</dd>
                    </div>
                    <div class="rateb-pos__totals-row rateb-pos__totals-row--grand">
                        <dt><?php echo __('pos_total'); ?></dt>
                        <dd data-pos-total>0.00</dd>
                    </div>
                </dl>
                <button type="button" class="rateb-pos__charge" data-pos-checkout-open disabled>
                    <span class="rateb-pos__charge-label"><?php echo __('pos_checkout'); ?></span>
                    <span class="rateb-pos__charge-amount" data-pos-pay-amount>0.00</span>
                </button>
                <?php if ($canReturns): ?>
                <div class="rateb-pos__ticket-utils">
                    <button type="button" class="rateb-pos__util-btn rateb-pos__util-btn--accent" data-pos-return-open><?php echo __('pos_return'); ?></button>
                    <button type="button" class="rateb-pos__util-btn rateb-pos__util-btn--accent" data-pos-exchange-open><?php echo __('pos_exchange'); ?></button>
                </div>
                <?php endif; ?>
                <span class="rateb-pos__ticket-count rateb-pos__ticket-count--sr" data-pos-cart-count>0</span>
            </footer>
        </aside>

        <main class="rateb-pos__catalog" aria-label="<?php echo __('pos_products'); ?>">
            <div class="rateb-pos__catalog-top">
                <div class="rateb-pos__search-unified">
                    <div class="rateb-pos__search-product" data-pos-product-combobox>
                        <span class="rateb-pos__search-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
                        </span>
                        <input type="search"
                               class="rateb-pos__search-input"
                               autocomplete="off"
                               placeholder="<?php echo __('pos_search_placeholder'); ?>"
                               data-pos-product-search
                               aria-label="<?php echo __('pos_search_placeholder'); ?>" />
                        <button type="button" class="rateb-pos__search-clear" data-pos-search-clear hidden aria-label="<?php echo __('clear'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        </button>
                        <ul class="rateb-pos__dropdown rateb-pos-combobox-list" role="listbox" hidden data-pos-product-list></ul>
                    </div>
                    <div class="rateb-pos__search-barcode">
                        <span class="rateb-pos__search-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h3M20 7V4h-3M4 17v3h3M20 17v3h-3"/><path d="M7 4h2v16H7zM15 4h2v16h-2z"/></svg>
                        </span>
                        <input type="text"
                               class="rateb-pos__search-input rateb-pos__search-input--barcode"
                               autocomplete="off"
                               inputmode="numeric"
                               placeholder="<?php echo __('pos_barcode_placeholder'); ?>"
                               title="<?php echo __('pos_barcode_scan'); ?>"
                               data-pos-barcode-input
                               aria-label="<?php echo __('pos_barcode_scan'); ?>" />
                    </div>
                </div>
                <div class="rateb-pos__cat-bar" data-pos-cat-bar>
                    <nav class="rateb-pos__cat-nav" data-pos-categories role="tablist"></nav>
                    <span class="rateb-pos__cat-indicator" data-pos-cat-indicator hidden aria-hidden="true"></span>
                </div>
            </div>

            <div class="rateb-pos__grid-wrap">
                <div class="rateb-pos__catalog-empty" data-pos-catalog-empty hidden role="status">
                    <div class="rateb-pos__catalog-empty-icon" aria-hidden="true">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7.7L12 12.5l8.7-4.8M12 22V12.5"/></svg>
                    </div>
                    <p class="rateb-pos__catalog-empty-title"><?php echo __('pos_catalog_empty'); ?></p>
                    <p class="rateb-pos__catalog-empty-hint"><?php echo __('pos_catalog_empty_hint'); ?></p>
                </div>
                <div class="rateb-pos__grid" data-pos-product-grid role="list">
                    <div class="rateb-pos__grid-spacer" data-pos-virtual-spacer aria-hidden="true"></div>
                    <div class="rateb-pos__grid-window" data-pos-virtual-window></div>
                </div>
            </div>
        </main>
    </div>

    <div class="rateb-pos__customer-panel" data-pos-customer-sheet hidden>
        <div class="rateb-pos__customer-panel-backdrop" data-pos-customer-sheet-close tabindex="-1" aria-hidden="true"></div>
        <div class="rateb-pos__customer-panel-inner" role="dialog" aria-labelledby="rateb-pos-customer-title">
            <header class="rateb-pos__customer-panel-head">
                <h2 id="rateb-pos-customer-title"><?php echo __('pos_customer'); ?></h2>
                <button type="button" class="rateb-pos__icon-btn" data-pos-customer-sheet-close aria-label="<?php echo __('close'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </header>
            <div class="rateb-pos__customer-quick">
                <button type="button" class="rateb-pos__customer-chip is-active" data-pos-customer-walkin><?php echo __('pos_walk_in_customer'); ?></button>
            </div>
            <div class="rateb-pos__customer-recent" data-pos-customer-recent-wrap hidden>
                <p class="rateb-pos__customer-section-label"><?php echo __('pos_customer'); ?></p>
                <div class="rateb-pos__customer-recent-list" data-pos-customer-recent></div>
            </div>
            <div class="rateb-pos__customer-search" data-pos-customer-combobox>
                <input type="search"
                       class="rateb-pos__input rateb-pos__input--block"
                       autocomplete="off"
                       placeholder="<?php echo __('pos_customer_search'); ?>"
                       data-pos-customer-input
                       aria-label="<?php echo __('pos_customer_search'); ?>" />
                <ul class="rateb-pos__dropdown rateb-pos-combobox-list" role="listbox" hidden data-pos-customer-list></ul>
            </div>
            <div class="rateb-pos__customer-quick" data-pos-customer-quick>
                <p class="rateb-pos__customer-section-label"><?php echo __('pos_customer_quick_add'); ?></p>
                <input type="text" class="rateb-pos__input rateb-pos__input--block" data-pos-customer-quick-name placeholder="<?php echo __('pos_customer_name'); ?>" maxlength="120" />
                <input type="tel" class="rateb-pos__input rateb-pos__input--block" data-pos-customer-quick-phone placeholder="<?php echo __('pos_customer_phone'); ?>" maxlength="30" />
                <button type="button" class="rateb-pos__customer-clear-btn" data-pos-customer-quick-add><?php echo __('pos_add_customer'); ?></button>
            </div>
            <p class="rateb-pos__customer-loyalty" data-pos-customer-loyalty-hint hidden></p>
            <p class="rateb-pos__customer-selected" data-pos-customer-display aria-live="polite"></p>
            <button type="button" class="rateb-pos__customer-clear-btn" data-pos-customer-clear><?php echo __('pos_customer_clear'); ?></button>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/pos-register-modes.php'; ?>
    <?php include __DIR__ . '/../partials/pos-register-cashier.php'; ?>

    <div class="rateb-pos__modal" data-pos-serial-modal hidden role="dialog" aria-modal="true" aria-labelledby="rateb-pos-serial-title">
        <div class="rateb-pos__modal-backdrop" data-pos-serial-close tabindex="-1" aria-hidden="true"></div>
        <div class="rateb-pos__modal-panel">
            <header class="rateb-pos__modal-head">
                <h2 id="rateb-pos-serial-title"><?php echo __('pos_select_serial'); ?></h2>
                <button type="button" class="rateb-pos__modal-close" data-pos-serial-close aria-label="<?php echo __('close'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </header>
            <div class="rateb-pos__modal-body" data-pos-serial-body></div>
        </div>
    </div>

    <div class="rateb-pos__modal" id="rateb-pos-receipt-modal" data-pos-receipt-modal hidden role="dialog" aria-modal="true">
        <div class="rateb-pos__modal-backdrop" data-pos-receipt-close tabindex="-1" aria-hidden="true"></div>
        <div class="rateb-pos__modal-panel rateb-pos__modal-panel--receipt">
            <header class="rateb-pos__modal-head">
                <h2><?php echo __('pos_receipt'); ?></h2>
                <div class="rateb-pos__modal-head-actions">
                    <button type="button" class="rateb-pos__split-btn" data-pos-receipt-print><?php echo __('pos_print_receipt'); ?></button>
                    <button type="button" class="rateb-pos__modal-close" data-pos-receipt-close aria-label="<?php echo __('close'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
                </div>
            </header>
            <div class="rateb-pos__modal-body rateb-pos__receipt-print-area" data-pos-receipt-body></div>
        </div>
    </div>

    <div class="rateb-pos__fly-layer" data-pos-fly-layer aria-hidden="true"></div>
    <div class="rateb-pos__toast" data-pos-status role="status" aria-live="polite" aria-atomic="true"></div>
</div>
