<?php
declare(strict_types=1);

/** @var bool $canReturns */
?>
<?php if ($canReturns): ?>
<div class="rateb-pos__service-panel rateb-pos__return-sheet" data-pos-return-panel hidden>
    <header class="rateb-pos__service-head">
        <button type="button" class="rateb-pos__service-back" data-pos-return-close aria-label="<?php echo __('close'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <h2 data-pos-ops-panel-title><?php echo __('pos_return'); ?></h2>
    </header>
    <div class="rateb-pos__return-body">
        <div class="rateb-pos__return-search">
            <input type="search" id="rateb-pos-order-search" class="rateb-pos__input rateb-pos__input--block"
                   autocomplete="off" placeholder="<?php echo __('pos_search_order_placeholder'); ?>"
                   data-pos-order-search aria-label="<?php echo __('pos_search_order'); ?>" />
            <input type="text" class="rateb-pos__input rateb-pos__input--block" placeholder="<?php echo __('pos_barcode_placeholder'); ?>"
                   data-pos-return-barcode aria-label="<?php echo __('pos_barcode_scan'); ?>" />
            <div class="rateb-pos__field" data-pos-order-combobox>
                <ul class="rateb-pos__dropdown rateb-pos-combobox-list" role="listbox" hidden data-pos-order-list></ul>
            </div>
        </div>
        <p class="rateb-pos__return-order" data-pos-selected-order aria-live="polite"></p>
        <div class="rateb-pos__return-lines" data-pos-return-lines-wrap hidden>
            <div class="rateb-pos__return-lines-list" data-pos-return-lines-body role="list"></div>
        </div>
        <p class="rateb-pos__hint" data-pos-exchange-cart-note hidden><?php echo __('pos_exchange_cart_hint'); ?></p>
        <div class="rateb-pos__return-refund" data-pos-return-settlement>
            <p class="rateb-pos__field-label" data-pos-settlement-label><?php echo __('pos_refund_method'); ?></p>
            <div class="rateb-pos__tender-grid">
                <button type="button" class="rateb-pos__tender is-active" data-pos-refund-pick="cash"><?php echo __('pos_refund_cash'); ?></button>
                <button type="button" class="rateb-pos__tender" data-pos-refund-pick="card"><?php echo __('pos_refund_card'); ?></button>
                <button type="button" class="rateb-pos__tender" data-pos-refund-pick="bank"><?php echo __('pos_refund_bank'); ?></button>
                <button type="button" class="rateb-pos__tender" data-pos-refund-pick="wallet"><?php echo __('pos_refund_wallet'); ?></button>
            </div>
            <select class="visually-hidden" data-pos-return-refund-method aria-hidden="true" tabindex="-1">
                <option value="cash" selected><?php echo __('pos_refund_cash'); ?></option>
                <option value="card"><?php echo __('pos_refund_card'); ?></option>
                <option value="bank"><?php echo __('pos_refund_bank'); ?></option>
                <option value="wallet"><?php echo __('pos_refund_wallet'); ?></option>
                <option value="store_credit"><?php echo __('pos_refund_store_credit'); ?></option>
                <option value="gift_card"><?php echo __('pos_refund_gift_card'); ?></option>
            </select>
        </div>
        <div class="rateb-pos__return-extra" data-pos-exchange-rewards hidden>
            <input type="text" class="rateb-pos__input rateb-pos__input--block" data-pos-exchange-coupon maxlength="40" placeholder="<?php echo __('pos_coupon_code'); ?>" />
            <input type="number" min="0" step="0.01" class="rateb-pos__input rateb-pos__input--block" data-pos-exchange-points value="0" placeholder="<?php echo __('pos_loyalty_points'); ?>" />
        </div>
        <div data-pos-exchange-payments hidden>
            <button type="button" class="rateb-pos__modes-item" data-pos-exchange-add-payment><?php echo __('pos_add_payment'); ?></button>
        </div>
        <p class="rateb-pos__return-summary" data-pos-ops-net-summary hidden></p>
        <button type="button" class="rateb-pos__charge rateb-pos__charge--sm" data-pos-return-submit><?php echo __('pos_process_return'); ?></button>
        <button type="button" class="rateb-pos__charge rateb-pos__charge--sm" data-pos-exchange-submit hidden><?php echo __('pos_process_exchange'); ?></button>
    </div>
</div>
<?php endif; ?>

<div class="rateb-pos__service-panel rateb-pos__pay-sheet" data-pos-checkout-panel hidden>
    <header class="rateb-pos__pay-head">
        <button type="button" class="rateb-pos__service-back" data-pos-checkout-close aria-label="<?php echo __('close'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <h2><?php echo __('pos_checkout'); ?></h2>
        <span class="rateb-pos__pay-head-total" data-pos-pay-sheet-total>0.00</span>
    </header>
    <div class="rateb-pos__pay-body">
        <div class="rateb-pos__pay-main">
            <div class="rateb-pos__pay-due">
                <span class="rateb-pos__pay-due-label"><?php echo __('pos_total'); ?></span>
                <span class="rateb-pos__pay-due-value" data-pos-pay-due>0.00</span>
            </div>
            <div class="rateb-pos__pay-tenders" data-pos-payment-methods role="group" aria-label="<?php echo __('pos_payment_method'); ?>">
                <button type="button" class="rateb-pos__tender is-active" data-pos-tender-pick="cash"><?php echo __('pos_refund_cash'); ?></button>
                <button type="button" class="rateb-pos__tender" data-pos-tender-pick="card"><?php echo __('pos_refund_card'); ?></button>
                <button type="button" class="rateb-pos__tender" data-pos-tender-pick="bank"><?php echo __('pos_refund_bank'); ?></button>
                <button type="button" class="rateb-pos__tender" data-pos-tender-pick="wallet"><?php echo __('pos_refund_wallet'); ?></button>
                <button type="button" class="rateb-pos__tender" data-pos-tender-pick="gift_card"><?php echo __('pos_refund_gift_card'); ?></button>
            </div>
            <div class="rateb-pos__pay-cash-keys" data-pos-cash-shortcuts>
                <button type="button" class="rateb-pos__cash-key" data-pos-cash-exact><?php echo __('pos_total'); ?></button>
                <button type="button" class="rateb-pos__cash-key" data-pos-cash-amt="50">50</button>
                <button type="button" class="rateb-pos__cash-key" data-pos-cash-amt="100">100</button>
                <button type="button" class="rateb-pos__cash-key" data-pos-cash-amt="200">200</button>
            </div>
            <div class="rateb-pos__pay-amount-wrap">
                <label class="rateb-pos__field-label" for="rateb-pos-pay-active-amt"><?php echo __('pos_payment_amount'); ?></label>
                <input type="text" inputmode="decimal" class="rateb-pos__pay-amount-input" id="rateb-pos-pay-active-amt" data-pos-active-pay-amount value="0.00" readonly />
            </div>
            <div class="rateb-pos__pay-change" data-pos-change-wrap hidden>
                <span><?php echo rateb_is_rtl() ? 'الباقي' : 'Change'; ?></span>
                <strong data-pos-change-due>0.00</strong>
            </div>
            <div class="rateb-pos__pay-split">
                <button type="button" class="rateb-pos__split-btn" data-pos-add-payment><?php echo __('pos_add_payment'); ?></button>
            </div>
            <div class="rateb-pos__pay-rows" data-pos-payment-list></div>
            <div class="rateb-pos__pay-gift-card" data-pos-gift-card-panel hidden>
                <label class="rateb-pos__field-label" for="rateb-pos-gift-card-code"><?php echo __('pos_gift_card_code'); ?></label>
                <div class="rateb-pos__pay-gift-row">
                    <input type="text" id="rateb-pos-gift-card-code" class="rateb-pos__input" data-pos-gift-card-code maxlength="40" placeholder="<?php echo __('pos_gift_card_code'); ?>" />
                    <button type="button" class="rateb-pos__split-btn" data-pos-gift-card-validate><?php echo __('pos_gift_card_validate'); ?></button>
                </div>
                <p class="rateb-pos__hint" data-pos-gift-card-balance hidden></p>
            </div>
            <dl class="rateb-pos__checkout-summary" data-pos-checkout-summary></dl>
            <div class="rateb-pos__pay-rewards" data-pos-rewards-panel>
                <div class="rateb-pos__pay-rewards-row">
                    <label class="rateb-pos__field-label" for="rateb-pos-invoice-disc-value"><?php echo __('pos_invoice_discount'); ?></label>
                    <div class="rateb-pos__pay-disc-row">
                        <select id="rateb-pos-invoice-disc-type" class="rateb-pos__input rateb-pos__input--sm" data-pos-invoice-discount-type>
                            <option value="amount"><?php echo __('pos_discount_amount'); ?></option>
                            <option value="percent"><?php echo __('pos_discount_percent'); ?></option>
                        </select>
                        <input type="number" id="rateb-pos-invoice-disc-value" class="rateb-pos__input rateb-pos__input--sm" data-pos-invoice-discount-value value="0" min="0" step="0.01" inputmode="decimal" />
                    </div>
                </div>
                <div class="rateb-pos__pay-rewards-row">
                    <label class="rateb-pos__field-label" for="rateb-pos-coupon-code"><?php echo __('pos_coupon_code'); ?></label>
                    <div class="rateb-pos__pay-disc-row">
                        <input type="text" id="rateb-pos-coupon-code" class="rateb-pos__input" data-pos-coupon-code maxlength="40" placeholder="<?php echo __('pos_coupon_code'); ?>" />
                        <button type="button" class="rateb-pos__split-btn" data-pos-apply-coupon><?php echo __('pos_apply_coupon'); ?></button>
                    </div>
                    <p class="rateb-pos__hint" data-pos-coupon-msg hidden></p>
                </div>
                <div class="rateb-pos__pay-rewards-row">
                    <label class="rateb-pos__field-label" for="rateb-pos-points-redeem"><?php echo __('pos_loyalty_points'); ?></label>
                    <input type="number" id="rateb-pos-points-redeem" class="rateb-pos__input rateb-pos__input--block" data-pos-points-redeem value="0" min="0" step="1" inputmode="numeric" />
                    <p class="rateb-pos__hint" data-pos-loyalty-balance><?php echo __('pos_loyalty_balance'); ?>: —</p>
                </div>
                <label class="rateb-pos__pay-gift-receipt">
                    <input type="checkbox" data-pos-gift-receipt />
                    <span><?php echo __('pos_gift_receipt'); ?></span>
                </label>
            </div>
        </div>
        <div class="rateb-pos__pay-side">
            <div class="rateb-pos__keypad" data-pos-keypad>
                <button type="button" data-pos-key="1">1</button>
                <button type="button" data-pos-key="2">2</button>
                <button type="button" data-pos-key="3">3</button>
                <button type="button" data-pos-key="4">4</button>
                <button type="button" data-pos-key="5">5</button>
                <button type="button" data-pos-key="6">6</button>
                <button type="button" data-pos-key="7">7</button>
                <button type="button" data-pos-key="8">8</button>
                <button type="button" data-pos-key="9">9</button>
                <button type="button" data-pos-key=".">.</button>
                <button type="button" data-pos-key="0">0</button>
                <button type="button" data-pos-key="back" aria-label="<?php echo __('back'); ?>">⌫</button>
            </div>
            <button type="button" class="rateb-pos__charge rateb-pos__charge--complete" data-pos-checkout-complete><?php echo __('pos_complete_sale'); ?></button>
        </div>
    </div>
</div>

<ul class="visually-hidden" data-pos-shortcuts-list aria-hidden="true"></ul>
