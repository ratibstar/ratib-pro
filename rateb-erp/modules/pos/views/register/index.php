<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
$registerReady = (bool) ($context['register_ready'] ?? false);
$canReturns = (bool) ($context['can_returns'] ?? false);
$shift = $context['shift'] ?? null;
$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
?>
<div class="rateb-pos<?php echo $registerReady ? '' : ' rateb-pos--locked'; ?>"
     data-pos-register
     data-register-ready="<?php echo $registerReady ? '1' : '0'; ?>"
     aria-live="polite">

    <?php if (!$registerReady): ?>
    <div class="rateb-pos__shift-gate" role="dialog" aria-modal="true" aria-labelledby="rateb-pos-shift-title">
        <div class="rateb-pos__shift-gate-inner">
            <div class="rateb-pos__shift-gate-icon" aria-hidden="true"><i class="fa-solid fa-cash-register"></i></div>
            <h1 id="rateb-pos-shift-title" class="rateb-pos__shift-gate-title"><?php echo __('pos_shift_not_open'); ?></h1>
            <p class="rateb-pos__shift-gate-text"><?php echo __('pos_open_shift_link'); ?></p>
            <p class="rateb-pos__shift-gate-meta"><?php echo __('pos_context_shift'); ?>: <?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php include __DIR__ . '/../partials/pos-register-header.php'; ?>

    <div class="rateb-pos__stage">
        <aside class="rateb-pos__rail" aria-label="<?php echo __('pos_categories'); ?>">
            <div class="rateb-pos__rail-scroll">
                <nav class="rateb-pos__rail-nav" data-pos-categories role="tablist"></nav>
                <span class="rateb-pos__rail-indicator" data-pos-cat-indicator hidden aria-hidden="true"></span>
            </div>
        </aside>

        <main class="rateb-pos__catalog" aria-label="<?php echo __('pos_products'); ?>">
            <div class="rateb-pos__search">
                <div class="rateb-pos__search-product" data-pos-product-combobox>
                    <i class="fa-solid fa-magnifying-glass rateb-pos__search-icon" aria-hidden="true"></i>
                    <input type="search"
                           class="rateb-pos__search-input"
                           autocomplete="off"
                           placeholder="<?php echo __('pos_search_placeholder'); ?>"
                           data-pos-product-search
                           aria-label="<?php echo __('pos_search_placeholder'); ?>" />
                    <button type="button" class="rateb-pos__search-clear" data-pos-search-clear hidden aria-label="<?php echo __('clear'); ?>">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                    <ul class="rateb-pos__dropdown rateb-pos-combobox-list" role="listbox" hidden data-pos-product-list></ul>
                </div>
                <div class="rateb-pos__search-barcode">
                    <i class="fa-solid fa-barcode rateb-pos__search-icon" aria-hidden="true"></i>
                    <input type="text"
                           class="rateb-pos__search-input rateb-pos__search-input--barcode"
                           autocomplete="off"
                           inputmode="numeric"
                           placeholder="<?php echo __('pos_barcode_placeholder'); ?>"
                           data-pos-barcode-input
                           aria-label="<?php echo __('pos_barcode_scan'); ?>" />
                </div>
            </div>

            <div class="rateb-pos__grid-wrap">
                <div class="rateb-pos__grid" data-pos-product-grid role="list">
                    <div class="rateb-pos__grid-spacer" data-pos-virtual-spacer aria-hidden="true"></div>
                    <div class="rateb-pos__grid-window" data-pos-virtual-window></div>
                </div>
            </div>
        </main>

        <aside class="rateb-pos__ticket" aria-label="<?php echo __('pos_cart'); ?>">
            <header class="rateb-pos__ticket-head">
                <h2 class="rateb-pos__ticket-title"><?php echo __('pos_cart'); ?></h2>
                <span class="rateb-pos__ticket-count" data-pos-cart-count>0</span>
            </header>

            <div class="rateb-pos__ticket-lines" data-pos-cart-lines role="list"></div>
            <p class="rateb-pos__ticket-empty" data-pos-cart-empty><?php echo __('pos_cart_empty'); ?></p>

            <footer class="rateb-pos__ticket-foot">
                <dl class="rateb-pos__totals">
                    <div class="rateb-pos__totals-row">
                        <dt><?php echo __('pos_subtotal'); ?></dt>
                        <dd data-pos-subtotal>0.00</dd>
                    </div>
                    <div class="rateb-pos__totals-row rateb-pos__totals-row--muted">
                        <dt><?php echo __('pos_discount_total'); ?></dt>
                        <dd data-pos-discount-total>0.00</dd>
                    </div>
                    <div class="rateb-pos__totals-row rateb-pos__totals-row--muted">
                        <dt><?php echo __('pos_tax'); ?></dt>
                        <dd data-pos-tax>0.00</dd>
                    </div>
                    <div class="rateb-pos__totals-row rateb-pos__totals-row--total">
                        <dt><?php echo __('pos_total'); ?></dt>
                        <dd data-pos-total>0.00</dd>
                    </div>
                </dl>
                <button type="button" class="rateb-pos__charge" data-pos-checkout-open disabled>
                    <span class="rateb-pos__charge-label"><?php echo __('pos_checkout'); ?></span>
                    <span class="rateb-pos__charge-amount" data-pos-pay-amount>0.00</span>
                </button>
            </footer>
        </aside>
    </div>

    <div class="rateb-pos__customer-sheet" data-pos-customer-sheet hidden>
        <div class="rateb-pos__customer-sheet-backdrop" data-pos-customer-sheet-close tabindex="-1" aria-hidden="true"></div>
        <div class="rateb-pos__customer-sheet-panel" role="dialog" aria-labelledby="rateb-pos-customer-title">
            <header class="rateb-pos__customer-sheet-head">
                <h2 id="rateb-pos-customer-title"><?php echo __('pos_customer'); ?></h2>
                <button type="button" class="rateb-pos__customer-sheet-close" data-pos-customer-sheet-close aria-label="<?php echo __('close'); ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
            <div class="rateb-pos__customer-sheet-body" data-pos-customer-combobox>
                <input type="search"
                       class="rateb-pos__input rateb-pos__input--block"
                       autocomplete="off"
                       placeholder="<?php echo __('pos_customer_search'); ?>"
                       data-pos-customer-input
                       aria-label="<?php echo __('pos_customer_search'); ?>" />
                <ul class="rateb-pos__dropdown rateb-pos-combobox-list" role="listbox" hidden data-pos-customer-list></ul>
                <p class="rateb-pos__customer-selected" data-pos-customer-display aria-live="polite"></p>
                <button type="button" class="rateb-pos__modes-item" data-pos-customer-clear><?php echo __('pos_customer_clear'); ?></button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/pos-register-modes.php'; ?>

    <div class="rateb-pos__modal" data-pos-serial-modal hidden role="dialog" aria-modal="true" aria-labelledby="rateb-pos-serial-title">
        <div class="rateb-pos__modal-backdrop" data-pos-serial-close tabindex="-1" aria-hidden="true"></div>
        <div class="rateb-pos__modal-panel">
            <header class="rateb-pos__modal-head">
                <h2 id="rateb-pos-serial-title"><?php echo __('pos_select_serial'); ?></h2>
                <button type="button" class="rateb-pos__modal-close" data-pos-serial-close aria-label="<?php echo __('close'); ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
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
                <button type="button" class="rateb-pos__modal-close" data-pos-receipt-close aria-label="<?php echo __('close'); ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
            <div class="rateb-pos__modal-body" data-pos-receipt-body></div>
        </div>
    </div>

    <div class="rateb-pos__fly-layer" data-pos-fly-layer aria-hidden="true"></div>
    <div class="rateb-pos__toast" data-pos-status role="status" aria-live="polite" aria-atomic="true"></div>
</div>
