<?php
declare(strict_types=1);

/** @var bool $canReturns */
?>
<div class="rateb-pos__modes-menu" id="rateb-pos-modes-menu" data-pos-modes-menu hidden>
    <button type="button" class="rateb-pos__modes-item" data-pos-suspend><i class="fa-solid fa-pause"></i><?php echo __('pos_suspend_sale'); ?></button>
    <button type="button" class="rateb-pos__modes-item" data-pos-save-quote><i class="fa-solid fa-file-lines"></i><?php echo __('pos_save_quote'); ?></button>
    <?php if ($canReturns): ?>
    <button type="button" class="rateb-pos__modes-item" data-pos-return-open><i class="fa-solid fa-rotate-left"></i><?php echo __('pos_return'); ?></button>
    <button type="button" class="rateb-pos__modes-item" data-pos-exchange-open><i class="fa-solid fa-right-left"></i><?php echo __('pos_exchange'); ?></button>
    <?php endif; ?>
    <button type="button" class="rateb-pos__modes-item" data-pos-new-sale><i class="fa-solid fa-plus"></i><?php echo __('pos_new_sale'); ?></button>
    <button type="button" class="rateb-pos__modes-item rateb-pos__modes-item--danger" data-pos-clear-cart><i class="fa-solid fa-trash-can"></i><?php echo __('pos_clear_cart'); ?></button>
</div>

<?php if ($canReturns): ?>
<div class="rateb-pos__service-panel" data-pos-return-panel hidden>
    <header class="rateb-pos__service-head">
        <button type="button" class="rateb-pos__service-back" data-pos-return-close aria-label="<?php echo __('close'); ?>"><i class="fa-solid fa-arrow-left"></i></button>
        <h2 data-pos-ops-panel-title><?php echo __('pos_return'); ?></h2>
    </header>
    <div class="rateb-pos__service-body">
        <label for="rateb-pos-order-search"><?php echo __('pos_search_order'); ?></label>
        <div class="rateb-pos__field" data-pos-order-combobox>
            <input type="search" id="rateb-pos-order-search" class="rateb-pos__input rateb-pos__input--block"
                   autocomplete="off" placeholder="<?php echo __('pos_search_order_placeholder'); ?>"
                   data-pos-order-search />
            <ul class="rateb-pos__dropdown rateb-pos-combobox-list" role="listbox" hidden data-pos-order-list></ul>
        </div>
        <p data-pos-selected-order aria-live="polite"></p>
        <div data-pos-return-lines-wrap hidden>
            <table class="rateb-pos__service-table">
                <thead><tr><th><?php echo __('pos_item_name'); ?></th><th><?php echo __('pos_returnable_qty'); ?></th><th><?php echo __('pos_qty'); ?></th></tr></thead>
                <tbody data-pos-return-lines-body></tbody>
            </table>
        </div>
        <p class="rateb-pos__hint" data-pos-exchange-cart-note hidden><?php echo __('pos_exchange_cart_hint'); ?></p>
        <div class="rateb-pos__field" data-pos-return-settlement>
            <label data-pos-settlement-label><?php echo __('pos_refund_method'); ?></label>
            <select class="rateb-pos__input rateb-pos__input--block" data-pos-return-refund-method>
                <option value="cash"><?php echo __('pos_refund_cash'); ?></option>
                <option value="card"><?php echo __('pos_refund_card'); ?></option>
                <option value="bank"><?php echo __('pos_refund_bank'); ?></option>
                <option value="wallet"><?php echo __('pos_refund_wallet'); ?></option>
                <option value="store_credit"><?php echo __('pos_refund_store_credit'); ?></option>
                <option value="gift_card"><?php echo __('pos_refund_gift_card'); ?></option>
            </select>
        </div>
        <div class="rateb-pos__field" data-pos-exchange-rewards hidden>
            <label><?php echo __('pos_coupon_code'); ?></label>
            <input type="text" class="rateb-pos__input rateb-pos__input--block" data-pos-exchange-coupon maxlength="40" />
            <label><?php echo __('pos_loyalty_points'); ?></label>
            <input type="number" min="0" step="0.01" class="rateb-pos__input rateb-pos__input--block" data-pos-exchange-points value="0" />
        </div>
        <div data-pos-exchange-payments hidden>
            <button type="button" class="rateb-pos__modes-item" data-pos-exchange-add-payment><?php echo __('pos_add_payment'); ?></button>
        </div>
        <p class="rateb-pos__hint" data-pos-ops-net-summary hidden></p>
        <button type="button" class="rateb-pos__modes-item" data-pos-return-submit><?php echo __('pos_process_return'); ?></button>
        <button type="button" class="rateb-pos__modes-item" data-pos-exchange-submit hidden><?php echo __('pos_process_exchange'); ?></button>
    </div>
</div>
<?php endif; ?>

<div class="rateb-pos__service-panel" data-pos-checkout-panel hidden>
    <header class="rateb-pos__service-head">
        <button type="button" class="rateb-pos__service-back" data-pos-checkout-close aria-label="<?php echo __('close'); ?>"><i class="fa-solid fa-arrow-left"></i></button>
        <h2><?php echo __('pos_checkout'); ?></h2>
    </header>
    <div class="rateb-pos__service-body">
        <div class="rateb-pos__field">
            <label for="rateb-pos-invoice-disc-type"><?php echo __('pos_invoice_discount'); ?></label>
            <div class="rateb-pos__field-row">
                <select id="rateb-pos-invoice-disc-type" class="rateb-pos__input rateb-pos__input--block" data-pos-invoice-discount-type>
                    <option value="amount"><?php echo __('pos_discount_amount'); ?></option>
                    <option value="percent"><?php echo __('pos_discount_percent'); ?></option>
                </select>
                <input type="number" min="0" step="0.01" class="rateb-pos__input rateb-pos__input--block" data-pos-invoice-discount-value value="0" />
            </div>
        </div>
        <div data-pos-rewards-panel>
            <div class="rateb-pos__field">
                <label><?php echo __('pos_coupon_code'); ?></label>
                <div class="rateb-pos__field-row">
                    <input type="text" class="rateb-pos__input rateb-pos__input--block" data-pos-coupon-code maxlength="40" />
                    <button type="button" class="rateb-pos__modes-item" data-pos-apply-coupon><?php echo __('pos_apply_coupon'); ?></button>
                </div>
                <p class="rateb-pos__hint" data-pos-coupon-msg hidden></p>
            </div>
            <div class="rateb-pos__field">
                <label><?php echo __('pos_loyalty_points'); ?></label>
                <input type="number" min="0" step="0.01" class="rateb-pos__input rateb-pos__input--block" data-pos-points-redeem value="0" />
                <p class="rateb-pos__hint" data-pos-loyalty-balance><?php echo __('pos_loyalty_balance'); ?>: —</p>
            </div>
        </div>
        <dl class="rateb-pos__checkout-summary" data-pos-checkout-summary></dl>
        <div data-pos-payment-list></div>
        <div class="rateb-pos__field-row">
            <button type="button" class="rateb-pos__modes-item" data-pos-add-payment><?php echo __('pos_add_payment'); ?></button>
            <button type="button" class="rateb-pos__charge rateb-pos__charge--sm" data-pos-checkout-complete><?php echo __('pos_complete_sale'); ?></button>
        </div>
    </div>
</div>

<input type="checkbox" data-pos-gift-receipt hidden aria-hidden="true" tabindex="-1" />
<ul class="visually-hidden" data-pos-shortcuts-list aria-hidden="true"></ul>
<div class="rateb-pos__suspended" data-pos-suspended-list aria-hidden="true"></div>
