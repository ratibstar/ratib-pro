<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var array<string, mixed> $session */
/** @var array<string, mixed> $totals */
$registerReady = !empty($context['register_ready']);
$shiftUrl = rateb_app_url('pos/shifts/open');
$uiLabels = json_encode([
    'pos_cat_all' => __('pos_cat_all'),
    'pos_cat_coffee' => __('pos_cat_coffee'),
    'pos_cat_food' => __('pos_cat_food'),
    'pos_cat_desserts' => __('pos_cat_desserts'),
    'pos_cat_drinks' => __('pos_cat_drinks'),
    'pos_cat_bakery' => __('pos_cat_bakery'),
    'pos_cat_pizza' => __('pos_cat_pizza'),
    'pos_cat_burger' => __('pos_cat_burger'),
    'pos_cat_offers' => __('pos_cat_offers'),
    'pos_cat_favorites' => __('pos_cat_favorites'),
    'pos_cat_recent' => __('pos_cat_recent'),
    'pos_cat_popular' => __('pos_cat_popular'),
    'pos_online' => __('pos_online'),
    'pos_offline' => __('pos_offline'),
    'pos_low_stock' => __('pos_low_stock'),
    'pos_favorite' => __('pos_favorite'),
    'pos_voice_search' => __('pos_voice_search'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script type="application/json" id="rateb-pos-ui-labels"><?php echo $uiLabels; ?></script>
<div class="rateb-pos-register rateb-pos-register--premium rateb-pos-register--pro" id="rateb-pos-register-main"
     data-pos-register data-pos-register--premium data-register-ready="<?php echo $registerReady ? '1' : '0'; ?>">
    <?php if (!$registerReady): ?>
    <div class="rateb-pos-alert rateb-pos-alert--warn" role="alert">
        <p><?php echo __('pos_no_shift_warning'); ?></p>
        <a class="rateb-pos-alert__link" href="<?php echo $shiftUrl; ?>"><?php echo __('pos_open_shift_link'); ?></a>
    </div>
    <?php endif; ?>

    <div class="rateb-pos-stage">
        <section class="rateb-pos-catalog" aria-label="<?php echo __('pos_product_search'); ?>">
            <div class="rateb-pos-search-hero" role="search">
                <label class="rateb-pos-search-hero__field rateb-pos-search-hero__field--product">
                    <span class="rateb-pos-search-hero__icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <span class="visually-hidden"><?php echo __('pos_product_search'); ?></span>
                    <div class="rateb-pos-combobox" data-pos-product-combobox>
                        <input type="search" id="rateb-pos-search-input" class="rateb-pos-search-hero__input"
                               autocomplete="off" role="combobox" aria-expanded="false" aria-controls="rateb-pos-product-list"
                               aria-autocomplete="list" placeholder="<?php echo __('pos_search_placeholder'); ?>"
                               data-pos-product-search />
                        <button type="button" class="rateb-pos-search-hero__clear" data-pos-search-clear hidden aria-label="<?php echo __('clear'); ?>">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                        <ul id="rateb-pos-product-list" class="rateb-pos-combobox-list rateb-pos-combobox-list--hero" role="listbox" hidden data-pos-product-list></ul>
                    </div>
                </label>
                <button type="button" class="rateb-pos-search-hero__shortcut" data-pos-focus-barcode aria-label="<?php echo __('pos_barcode_scan'); ?>">
                    <i class="fa-solid fa-barcode" aria-hidden="true"></i>
                </button>
                <button type="button" class="rateb-pos-search-hero__shortcut rateb-pos-search-hero__shortcut--voice" data-pos-voice-search aria-label="<?php echo __('pos_voice_search'); ?>" disabled>
                    <i class="fa-solid fa-microphone" aria-hidden="true"></i>
                </button>
            </div>

            <div class="rateb-pos-accessible-layer" aria-hidden="true">
                <input type="text" id="rateb-pos-barcode-input" class="rateb-pos-input"
                       inputmode="numeric" autocomplete="off" data-pos-barcode-input tabindex="-1" aria-hidden="true" />
            </div>

            <div class="rateb-pos-customer-popover" data-pos-customer-popover hidden role="dialog" aria-label="<?php echo __('pos_customer'); ?>">
                <div class="rateb-pos-combobox" data-pos-customer-combobox>
                    <input type="search" id="rateb-pos-customer-input" class="rateb-pos-search-hero__input rateb-pos-customer-popover__input"
                           autocomplete="off" role="combobox" aria-expanded="false" aria-controls="rateb-pos-customer-list"
                           aria-autocomplete="list" placeholder="<?php echo __('pos_customer_search'); ?>"
                           data-pos-customer-input />
                    <button type="button" class="rateb-pos-search-hero__clear" data-pos-customer-clear aria-label="<?php echo __('pos_customer_clear'); ?>">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                    <ul id="rateb-pos-customer-list" class="rateb-pos-combobox-list rateb-pos-combobox-list--hero" role="listbox" hidden data-pos-customer-list></ul>
                </div>
            </div>
            <p class="visually-hidden" data-pos-customer-display aria-live="polite"><?php echo __('pos_walk_in_customer'); ?></p>

            <div class="rateb-pos-category-rail">
                <div class="rateb-pos-category-rail__track" data-pos-categories aria-label="<?php echo __('pos_categories'); ?>" role="tablist"></div>
                <div class="rateb-pos-category-rail__indicator" data-pos-category-indicator aria-hidden="true"></div>
            </div>

            <div class="rateb-pos-product-grid" data-pos-product-grid role="grid"
                 aria-label="<?php echo __('pos_product_search'); ?>"></div>
        </section>

        <aside class="rateb-pos-float-cart" aria-labelledby="rateb-pos-cart-title">
            <header class="rateb-pos-float-cart__head">
                <h2 id="rateb-pos-cart-title" class="rateb-pos-float-cart__title"><?php echo __('pos_cart'); ?></h2>
                <span class="rateb-pos-float-cart__badge" data-pos-cart-count aria-live="polite">0</span>
            </header>

            <div class="rateb-pos-float-cart__body">
                <div class="rateb-pos-cart-lines" data-pos-cart-lines role="list" aria-label="<?php echo __('pos_cart'); ?>"></div>
                <p class="rateb-pos-cart-empty" data-pos-cart-empty><?php echo __('pos_cart_empty'); ?></p>
            </div>

            <div class="rateb-pos-summary-cards" aria-live="polite">
                <div class="rateb-pos-summary-card">
                    <span class="rateb-pos-summary-card__label"><?php echo __('pos_subtotal'); ?></span>
                    <span class="rateb-pos-summary-card__value" data-pos-subtotal>0.00</span>
                </div>
                <div class="rateb-pos-summary-card rateb-pos-summary-card--discount">
                    <span class="rateb-pos-summary-card__label"><?php echo __('pos_discount_total'); ?></span>
                    <span class="rateb-pos-summary-card__value" data-pos-discount-total>0.00</span>
                </div>
                <div class="rateb-pos-summary-card rateb-pos-summary-card--tax">
                    <span class="rateb-pos-summary-card__label"><?php echo __('pos_tax'); ?></span>
                    <span class="rateb-pos-summary-card__value" data-pos-tax>0.00</span>
                </div>
                <div class="rateb-pos-summary-card rateb-pos-summary-card--grand">
                    <span class="rateb-pos-summary-card__label"><?php echo __('pos_total'); ?></span>
                    <span class="rateb-pos-summary-card__value rateb-pos-summary-card__value--grand" data-pos-total>0.00</span>
                </div>
            </div>

            <footer class="rateb-pos-checkout-footer">
                <button type="button" class="rateb-pos-pay-btn" data-pos-checkout-open>
                    <span class="rateb-pos-pay-btn__label"><?php echo __('pos_checkout'); ?></span>
                    <span class="rateb-pos-pay-btn__amount" data-pos-pay-amount>0.00</span>
                </button>
                <div class="rateb-pos-checkout-footer__secondary" role="group" aria-label="<?php echo __('pos_actions'); ?>">
                    <button type="button" class="rateb-pos-sec-btn" data-pos-suspend><i class="fa-solid fa-pause" aria-hidden="true"></i><span><?php echo __('pos_suspend_sale'); ?></span></button>
                    <button type="button" class="rateb-pos-sec-btn" data-pos-save-quote><i class="fa-solid fa-file-lines" aria-hidden="true"></i><span><?php echo __('pos_save_quote'); ?></span></button>
                    <button type="button" class="rateb-pos-sec-btn" data-pos-more-actions aria-expanded="false" aria-controls="rateb-pos-more-menu"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i><span><?php echo __('pos_more_actions'); ?></span></button>
                </div>
                <div class="rateb-pos-more-menu" id="rateb-pos-more-menu" data-pos-more-menu hidden>
                    <?php if (function_exists('rateb_can') && rateb_can('pos.returns.manage')): ?>
                        <button type="button" class="rateb-pos-more-menu__item" data-pos-return-open><?php echo __('pos_return'); ?></button>
                        <button type="button" class="rateb-pos-more-menu__item" data-pos-exchange-open><?php echo __('pos_exchange'); ?></button>
                    <?php endif; ?>
                    <button type="button" class="rateb-pos-more-menu__item" data-pos-new-sale><?php echo __('pos_new_sale'); ?></button>
                    <button type="button" class="rateb-pos-more-menu__item rateb-pos-more-menu__item--danger" data-pos-clear-cart><?php echo __('pos_clear_cart'); ?></button>
                    <label class="rateb-pos-more-menu__item rateb-pos-more-menu__check">
                        <input type="checkbox" data-pos-gift-receipt /> <?php echo __('pos_gift_receipt'); ?>
                    </label>
                </div>
            </footer>

            <div class="rateb-pos-ops-panel rateb-pos-ops-panel--compact">
                <div class="rateb-pos-ops-list" data-pos-suspended-list aria-label="<?php echo __('pos_suspended_sales'); ?>"></div>
            </div>

            <?php if (function_exists('rateb_can') && rateb_can('pos.returns.manage')): ?>
            <div class="rateb-pos-return-panel" data-pos-return-panel hidden>
                <h3 class="rateb-pos-panel-title" data-pos-ops-panel-title><?php echo __('pos_return'); ?></h3>
                <div class="rateb-pos-field">
                    <label class="rateb-pos-label" for="rateb-pos-order-search"><?php echo __('pos_search_order'); ?></label>
                    <div class="rateb-pos-combobox" data-pos-order-combobox>
                        <input type="search" id="rateb-pos-order-search" class="rateb-pos-input"
                               autocomplete="off" placeholder="<?php echo __('pos_search_order_placeholder'); ?>"
                               data-pos-order-search />
                        <ul class="rateb-pos-combobox-list" role="listbox" hidden data-pos-order-list></ul>
                    </div>
                    <p class="rateb-pos-selected-customer" data-pos-selected-order aria-live="polite"></p>
                </div>
                <div class="rateb-pos-return-lines-wrap" data-pos-return-lines-wrap hidden>
                    <h4 class="rateb-pos-panel-title"><?php echo __('pos_return_lines'); ?></h4>
                    <table class="rateb-pos-cart-table rateb-pos-return-lines-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo __('pos_item_name'); ?></th>
                                <th scope="col"><?php echo __('pos_returnable_qty'); ?></th>
                                <th scope="col"><?php echo __('pos_qty'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-pos-return-lines-body></tbody>
                    </table>
                </div>
                <div class="rateb-pos-field" data-pos-exchange-cart-note hidden>
                    <p class="rateb-pos-hint"><?php echo __('pos_exchange_cart_hint'); ?></p>
                </div>
                <div class="rateb-pos-field" data-pos-return-settlement>
                    <label class="rateb-pos-label" data-pos-settlement-label><?php echo __('pos_refund_method'); ?></label>
                    <select class="rateb-pos-input" data-pos-return-refund-method>
                        <option value="cash"><?php echo __('pos_refund_cash'); ?></option>
                        <option value="card"><?php echo __('pos_refund_card'); ?></option>
                        <option value="bank"><?php echo __('pos_refund_bank'); ?></option>
                        <option value="wallet"><?php echo __('pos_refund_wallet'); ?></option>
                        <option value="store_credit"><?php echo __('pos_refund_store_credit'); ?></option>
                        <option value="gift_card"><?php echo __('pos_refund_gift_card'); ?></option>
                    </select>
                </div>
                <div class="rateb-pos-field" data-pos-exchange-rewards hidden>
                    <label class="rateb-pos-label"><?php echo __('pos_coupon_code'); ?></label>
                    <input type="text" class="rateb-pos-input" data-pos-exchange-coupon maxlength="40" />
                    <label class="rateb-pos-label mt-2"><?php echo __('pos_loyalty_points'); ?></label>
                    <input type="number" min="0" step="0.01" class="rateb-pos-input" data-pos-exchange-points value="0" />
                </div>
                <div data-pos-exchange-payments hidden>
                    <button type="button" class="rateb-pos-sec-btn mb-2" data-pos-exchange-add-payment><?php echo __('pos_add_payment'); ?></button>
                </div>
                <p class="rateb-pos-hint" data-pos-ops-net-summary hidden></p>
                <button type="button" class="rateb-pos-sec-btn" data-pos-return-submit><?php echo __('pos_process_return'); ?></button>
                <button type="button" class="rateb-pos-sec-btn" data-pos-exchange-submit hidden><?php echo __('pos_process_exchange'); ?></button>
            </div>
            <?php endif; ?>

            <div class="rateb-pos-checkout-panel" data-pos-checkout-panel hidden>
                <h3 class="rateb-pos-panel-title"><?php echo __('pos_checkout'); ?></h3>
                <div class="rateb-pos-checkout-grid">
                    <div>
                        <label class="rateb-pos-label" for="rateb-pos-invoice-disc-type"><?php echo __('pos_invoice_discount'); ?></label>
                        <div class="rateb-pos-payment-row">
                            <select id="rateb-pos-invoice-disc-type" class="rateb-pos-input" data-pos-invoice-discount-type>
                                <option value="amount"><?php echo __('pos_discount_amount'); ?></option>
                                <option value="percent"><?php echo __('pos_discount_percent'); ?></option>
                            </select>
                            <input type="number" min="0" step="0.01" class="rateb-pos-input" data-pos-invoice-discount-value value="0" />
                        </div>
                    </div>
                    <div class="rateb-pos-rewards-row" data-pos-rewards-panel>
                        <div class="rateb-pos-field">
                            <label class="rateb-pos-label"><?php echo __('pos_coupon_code'); ?></label>
                            <div class="rateb-pos-payment-row">
                                <input type="text" class="rateb-pos-input" data-pos-coupon-code maxlength="40" />
                                <button type="button" class="rateb-pos-sec-btn" data-pos-apply-coupon><?php echo __('pos_apply_coupon'); ?></button>
                            </div>
                            <p class="rateb-pos-hint" data-pos-coupon-msg hidden></p>
                        </div>
                        <div class="rateb-pos-field">
                            <label class="rateb-pos-label"><?php echo __('pos_loyalty_points'); ?></label>
                            <input type="number" min="0" step="0.01" class="rateb-pos-input" data-pos-points-redeem value="0" />
                            <p class="rateb-pos-hint" data-pos-loyalty-balance><?php echo __('pos_loyalty_balance'); ?>: —</p>
                        </div>
                    </div>
                    <dl class="rateb-pos-checkout-summary" data-pos-checkout-summary></dl>
                </div>
                <div data-pos-payment-list></div>
                <div class="rateb-pos-checkout-actions">
                    <button type="button" class="rateb-pos-sec-btn" data-pos-add-payment><?php echo __('pos_add_payment'); ?></button>
                    <button type="button" class="rateb-pos-sec-btn" data-pos-checkout-close><?php echo __('cancel'); ?></button>
                    <button type="button" class="rateb-pos-pay-btn rateb-pos-pay-btn--compact" data-pos-checkout-complete><?php echo __('pos_complete_sale'); ?></button>
                </div>
            </div>

            <p class="rateb-pos-selected-line visually-hidden" data-pos-selected-line aria-live="polite"></p>

            <details class="rateb-pos-shortcuts-help">
                <summary><?php echo __('pos_keyboard_shortcuts'); ?></summary>
                <ul class="rateb-pos-shortcuts-list" data-pos-shortcuts-list></ul>
            </details>
        </aside>
    </div>

    <div class="rateb-pos-fly-layer" data-pos-fly-layer aria-hidden="true"></div>
    <div class="rateb-pos-status" role="status" aria-live="polite" aria-atomic="true" data-pos-status></div>

    <dialog class="rateb-pos-modal" id="rateb-pos-serial-modal" aria-labelledby="rateb-pos-serial-title" data-pos-serial-modal>
        <form method="dialog" class="rateb-pos-modal-inner">
            <header class="rateb-pos-modal-header">
                <h2 id="rateb-pos-serial-title"><?php echo __('pos_select_serial'); ?></h2>
                <button type="button" class="rateb-pos-modal-close" data-pos-serial-close aria-label="<?php echo __('close'); ?>"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <p class="rateb-pos-modal-product" data-pos-serial-product></p>
            <ul class="rateb-pos-serial-list" role="listbox" data-pos-serial-list></ul>
            <footer class="rateb-pos-modal-footer">
                <button type="button" class="rateb-pos-sec-btn" data-pos-serial-close><?php echo __('cancel'); ?></button>
            </footer>
        </form>
    </dialog>

    <dialog class="rateb-pos-modal rateb-pos-receipt-modal" id="rateb-pos-receipt-modal" aria-labelledby="rateb-pos-receipt-title">
        <div class="rateb-pos-modal-inner">
            <header class="rateb-pos-modal-header">
                <h2 id="rateb-pos-receipt-title"><?php echo __('pos_receipt'); ?></h2>
                <button type="button" class="rateb-pos-modal-close" data-pos-receipt-close aria-label="<?php echo __('close'); ?>"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="rateb-pos-receipt-body" data-pos-receipt-body></div>
            <footer class="rateb-pos-modal-footer">
                <button type="button" class="rateb-pos-pay-btn rateb-pos-pay-btn--compact" data-pos-receipt-close><?php echo __('close'); ?></button>
            </footer>
        </div>
    </dialog>
</div>
