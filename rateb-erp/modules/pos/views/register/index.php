<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
$registerReady = !empty($context['register_ready']);
$shiftUrl = rateb_app_url('pos/shifts/open');
$canReturns = function_exists('rateb_can') && rateb_can('pos.returns.manage');
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
    'pos_search_no_results' => __('pos_search_no_results'),
    'pos_out_of_stock' => __('pos_out_of_stock'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script type="application/json" id="rateb-pos-ui-labels"><?php echo $uiLabels; ?></script>

<div class="rateb-pos-v2" id="rateb-pos-register-main"
     data-pos-register data-pos-register--premium data-register-ready="<?php echo $registerReady ? '1' : '0'; ?>">

    <?php \Rateb\App\Pos\Support\PosView::partial('pos-commercial-header', ['context' => $context ?? [], 'locale' => rateb_locale()]); ?>

    <?php if (!$registerReady): ?>
    <div class="rateb-pos-v2__alert" role="alert">
        <p><?php echo __('pos_no_shift_warning'); ?></p>
        <a href="<?php echo $shiftUrl; ?>"><?php echo __('pos_open_shift_link'); ?></a>
    </div>
    <?php endif; ?>

    <div class="rateb-pos-v2__workspace">
        <nav class="rateb-pos-v2__sidebar" aria-label="<?php echo __('pos_categories'); ?>">
            <div class="rateb-pos-v2__sidebar-inner">
                <div class="rateb-pos-v2__sidebar-scroll" data-pos-categories role="tablist"></div>
                <div class="rateb-pos-v2__cat-indicator" data-pos-cat-indicator aria-hidden="true"></div>
            </div>
        </nav>

        <section class="rateb-pos-v2__catalog" aria-label="<?php echo __('pos_product_search'); ?>">
            <div class="rateb-pos-v2__search-unified" role="search" aria-label="<?php echo __('pos_product_search'); ?>">
                <div class="rateb-pos-v2__search-unified-inner">
                    <div class="rateb-pos-v2__seg rateb-pos-v2__seg--product">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <div class="rateb-pos-v2__combobox" data-pos-product-combobox>
                            <input type="search" id="rateb-pos-search-input" class="rateb-pos-v2__input rateb-pos-v2__input--inline"
                                   autocomplete="off" role="combobox" aria-expanded="false"
                                   aria-controls="rateb-pos-product-list" aria-autocomplete="list"
                                   placeholder="<?php echo __('pos_search_placeholder'); ?>"
                                   data-pos-product-search />
                            <button type="button" class="rateb-pos-v2__input-clear" data-pos-search-clear hidden aria-label="<?php echo __('clear'); ?>">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                            <ul id="rateb-pos-product-list" class="rateb-pos-v2__dropdown rateb-pos-combobox-list" role="listbox" hidden data-pos-product-list></ul>
                        </div>
                    </div>
                    <span class="rateb-pos-v2__seg-divider" aria-hidden="true"></span>
                    <div class="rateb-pos-v2__seg rateb-pos-v2__seg--barcode">
                        <i class="fa-solid fa-barcode" aria-hidden="true"></i>
                        <input type="text" id="rateb-pos-barcode-input" class="rateb-pos-v2__input rateb-pos-v2__input--inline"
                               inputmode="numeric" autocomplete="off"
                               placeholder="<?php echo __('pos_barcode_scan'); ?>"
                               data-pos-barcode-input aria-label="<?php echo __('pos_barcode_scan'); ?>" />
                    </div>
                    <span class="rateb-pos-v2__seg-divider" aria-hidden="true"></span>
                    <div class="rateb-pos-v2__seg rateb-pos-v2__seg--customer">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        <div class="rateb-pos-v2__combobox" data-pos-customer-combobox>
                            <input type="search" id="rateb-pos-customer-input" class="rateb-pos-v2__input rateb-pos-v2__input--inline"
                                   autocomplete="off" role="combobox" aria-expanded="false"
                                   aria-controls="rateb-pos-customer-list" aria-autocomplete="list"
                                   placeholder="<?php echo __('pos_customer_search'); ?>"
                                   data-pos-customer-input />
                            <button type="button" class="rateb-pos-v2__input-clear" data-pos-customer-clear aria-label="<?php echo __('pos_customer_clear'); ?>">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                            <ul id="rateb-pos-customer-list" class="rateb-pos-v2__dropdown rateb-pos-combobox-list" role="listbox" hidden data-pos-customer-list></ul>
                        </div>
                    </div>
                    <button type="button" class="rateb-pos-v2__seg-btn" data-pos-voice-search disabled aria-label="<?php echo __('pos_voice_search'); ?>">
                        <i class="fa-solid fa-microphone" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <p class="visually-hidden" data-pos-customer-display aria-live="polite"><?php echo __('pos_walk_in_customer'); ?></p>

            <div class="rateb-pos-v2__grid-wrap">
                <div class="rateb-pos-v2__grid" data-pos-product-grid role="grid"
                     aria-label="<?php echo __('pos_product_search'); ?>"></div>
            </div>
        </section>

        <aside class="rateb-pos-v2__cart" aria-labelledby="rateb-pos-cart-title">
            <header class="rateb-pos-v2__cart-head">
                <h2 id="rateb-pos-cart-title" class="rateb-pos-v2__cart-title"><?php echo __('pos_cart'); ?></h2>
                <span class="rateb-pos-v2__cart-count" data-pos-cart-count aria-live="polite">0</span>
            </header>

            <div class="rateb-pos-v2__cart-scroll">
                <div class="rateb-pos-v2__cart-lines" data-pos-cart-lines role="list"></div>
                <div class="rateb-pos-v2__cart-empty" data-pos-cart-empty>
                    <i class="fa-solid fa-basket-shopping" aria-hidden="true"></i>
                    <p><?php echo __('pos_cart_empty'); ?></p>
                </div>
            </div>

            <div class="rateb-pos-v2__totals rateb-pos-v2__totals--card" aria-live="polite">
                <div class="rateb-pos-v2__total-row"><span><?php echo __('pos_subtotal'); ?></span><span data-pos-subtotal>0.00</span></div>
                <div class="rateb-pos-v2__total-row"><span><?php echo __('pos_discount_total'); ?></span><span data-pos-discount-total>0.00</span></div>
                <div class="rateb-pos-v2__total-row"><span><?php echo __('pos_tax'); ?></span><span data-pos-tax>0.00</span></div>
                <div class="rateb-pos-v2__total-row rateb-pos-v2__total-row--grand"><span><?php echo __('pos_total'); ?></span><span data-pos-total>0.00</span></div>
            </div>

            <div class="rateb-pos-v2__cart-actions">
                <button type="button" class="rateb-pos-v2__pay" data-pos-checkout-open>
                    <span><?php echo __('pos_checkout'); ?></span>
                    <strong data-pos-pay-amount>0.00</strong>
                </button>
                <div class="rateb-pos-v2__action-grid">
                    <button type="button" class="rateb-pos-v2__action" data-pos-suspend><i class="fa-solid fa-pause"></i><?php echo __('pos_suspend_sale'); ?></button>
                    <button type="button" class="rateb-pos-v2__action" data-pos-save-quote><i class="fa-solid fa-file-lines"></i><?php echo __('pos_save_quote'); ?></button>
                    <?php if ($canReturns): ?>
                    <button type="button" class="rateb-pos-v2__action" data-pos-return-open><i class="fa-solid fa-rotate-left"></i><?php echo __('pos_return'); ?></button>
                    <button type="button" class="rateb-pos-v2__action" data-pos-exchange-open><i class="fa-solid fa-right-left"></i><?php echo __('pos_exchange'); ?></button>
                    <?php endif; ?>
                    <button type="button" class="rateb-pos-v2__action" data-pos-new-sale><i class="fa-solid fa-plus"></i><?php echo __('pos_new_sale'); ?></button>
                    <button type="button" class="rateb-pos-v2__action rateb-pos-v2__action--danger" data-pos-clear-cart><i class="fa-solid fa-trash-can"></i><?php echo __('pos_clear_cart'); ?></button>
                </div>
                <label class="rateb-pos-v2__gift">
                    <input type="checkbox" data-pos-gift-receipt /> <?php echo __('pos_gift_receipt'); ?>
                </label>
            </div>

            <div class="rateb-pos-v2__suspended" data-pos-suspended-list aria-label="<?php echo __('pos_suspended_sales'); ?>"></div>

            <?php if ($canReturns): ?>
            <div class="rateb-pos-v2__panel" data-pos-return-panel hidden>
                <h3 class="rateb-pos-v2__panel-title" data-pos-ops-panel-title><?php echo __('pos_return'); ?></h3>
                <div class="rateb-pos-v2__field">
                    <label for="rateb-pos-order-search"><?php echo __('pos_search_order'); ?></label>
                    <div class="rateb-pos-v2__combobox" data-pos-order-combobox>
                        <input type="search" id="rateb-pos-order-search" class="rateb-pos-v2__input"
                               autocomplete="off" placeholder="<?php echo __('pos_search_order_placeholder'); ?>"
                               data-pos-order-search />
                        <ul class="rateb-pos-v2__dropdown rateb-pos-combobox-list" role="listbox" hidden data-pos-order-list></ul>
                    </div>
                    <p data-pos-selected-order aria-live="polite"></p>
                </div>
                <div data-pos-return-lines-wrap hidden>
                    <table class="rateb-pos-v2__table">
                        <thead><tr><th><?php echo __('pos_item_name'); ?></th><th><?php echo __('pos_returnable_qty'); ?></th><th><?php echo __('pos_qty'); ?></th></tr></thead>
                        <tbody data-pos-return-lines-body></tbody>
                    </table>
                </div>
                <p class="rateb-pos-v2__hint" data-pos-exchange-cart-note hidden><?php echo __('pos_exchange_cart_hint'); ?></p>
                <div class="rateb-pos-v2__field" data-pos-return-settlement>
                    <label data-pos-settlement-label><?php echo __('pos_refund_method'); ?></label>
                    <select class="rateb-pos-v2__input" data-pos-return-refund-method>
                        <option value="cash"><?php echo __('pos_refund_cash'); ?></option>
                        <option value="card"><?php echo __('pos_refund_card'); ?></option>
                        <option value="bank"><?php echo __('pos_refund_bank'); ?></option>
                        <option value="wallet"><?php echo __('pos_refund_wallet'); ?></option>
                        <option value="store_credit"><?php echo __('pos_refund_store_credit'); ?></option>
                        <option value="gift_card"><?php echo __('pos_refund_gift_card'); ?></option>
                    </select>
                </div>
                <div class="rateb-pos-v2__field" data-pos-exchange-rewards hidden>
                    <label><?php echo __('pos_coupon_code'); ?></label>
                    <input type="text" class="rateb-pos-v2__input" data-pos-exchange-coupon maxlength="40" />
                    <label><?php echo __('pos_loyalty_points'); ?></label>
                    <input type="number" min="0" step="0.01" class="rateb-pos-v2__input" data-pos-exchange-points value="0" />
                </div>
                <div data-pos-exchange-payments hidden>
                    <button type="button" class="rateb-pos-v2__action" data-pos-exchange-add-payment><?php echo __('pos_add_payment'); ?></button>
                </div>
                <p class="rateb-pos-v2__hint" data-pos-ops-net-summary hidden></p>
                <button type="button" class="rateb-pos-v2__action" data-pos-return-submit><?php echo __('pos_process_return'); ?></button>
                <button type="button" class="rateb-pos-v2__action" data-pos-exchange-submit hidden><?php echo __('pos_process_exchange'); ?></button>
            </div>
            <?php endif; ?>

            <div class="rateb-pos-v2__panel" data-pos-checkout-panel hidden>
                <h3 class="rateb-pos-v2__panel-title"><?php echo __('pos_checkout'); ?></h3>
                <div class="rateb-pos-v2__field">
                    <label for="rateb-pos-invoice-disc-type"><?php echo __('pos_invoice_discount'); ?></label>
                    <div class="rateb-pos-v2__field-row">
                        <select id="rateb-pos-invoice-disc-type" class="rateb-pos-v2__input" data-pos-invoice-discount-type>
                            <option value="amount"><?php echo __('pos_discount_amount'); ?></option>
                            <option value="percent"><?php echo __('pos_discount_percent'); ?></option>
                        </select>
                        <input type="number" min="0" step="0.01" class="rateb-pos-v2__input" data-pos-invoice-discount-value value="0" />
                    </div>
                </div>
                <div data-pos-rewards-panel>
                    <div class="rateb-pos-v2__field">
                        <label><?php echo __('pos_coupon_code'); ?></label>
                        <div class="rateb-pos-v2__field-row">
                            <input type="text" class="rateb-pos-v2__input" data-pos-coupon-code maxlength="40" />
                            <button type="button" class="rateb-pos-v2__action" data-pos-apply-coupon><?php echo __('pos_apply_coupon'); ?></button>
                        </div>
                        <p class="rateb-pos-v2__hint" data-pos-coupon-msg hidden></p>
                    </div>
                    <div class="rateb-pos-v2__field">
                        <label><?php echo __('pos_loyalty_points'); ?></label>
                        <input type="number" min="0" step="0.01" class="rateb-pos-v2__input" data-pos-points-redeem value="0" />
                        <p class="rateb-pos-v2__hint" data-pos-loyalty-balance><?php echo __('pos_loyalty_balance'); ?>: —</p>
                    </div>
                </div>
                <dl class="rateb-pos-v2__checkout-summary" data-pos-checkout-summary></dl>
                <div data-pos-payment-list></div>
                <div class="rateb-pos-v2__checkout-btns">
                    <button type="button" class="rateb-pos-v2__action" data-pos-add-payment><?php echo __('pos_add_payment'); ?></button>
                    <button type="button" class="rateb-pos-v2__action" data-pos-checkout-close><?php echo __('cancel'); ?></button>
                    <button type="button" class="rateb-pos-v2__pay rateb-pos-v2__pay--sm" data-pos-checkout-complete><?php echo __('pos_complete_sale'); ?></button>
                </div>
            </div>

            <p class="visually-hidden" data-pos-selected-line aria-live="polite"></p>
            <details class="rateb-pos-v2__shortcuts">
                <summary><?php echo __('pos_keyboard_shortcuts'); ?></summary>
                <ul data-pos-shortcuts-list></ul>
            </details>
        </aside>
    </div>

    <div class="rateb-pos-v2__fly" data-pos-fly-layer aria-hidden="true"></div>
    <div class="rateb-pos-v2__toast" role="status" aria-live="polite" data-pos-status></div>

    <dialog class="rateb-pos-v2__dialog" id="rateb-pos-serial-modal" aria-labelledby="rateb-pos-serial-title" data-pos-serial-modal>
        <form method="dialog" class="rateb-pos-v2__dialog-inner">
            <header><h2 id="rateb-pos-serial-title"><?php echo __('pos_select_serial'); ?></h2>
                <button type="button" data-pos-serial-close aria-label="<?php echo __('close'); ?>"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <p data-pos-serial-product></p>
            <ul role="listbox" data-pos-serial-list></ul>
            <footer><button type="button" class="rateb-pos-v2__action" data-pos-serial-close><?php echo __('cancel'); ?></button></footer>
        </form>
    </dialog>

    <dialog class="rateb-pos-v2__dialog" id="rateb-pos-receipt-modal" aria-labelledby="rateb-pos-receipt-title">
        <div class="rateb-pos-v2__dialog-inner">
            <header><h2 id="rateb-pos-receipt-title"><?php echo __('pos_receipt'); ?></h2>
                <button type="button" data-pos-receipt-close aria-label="<?php echo __('close'); ?>"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div data-pos-receipt-body></div>
            <footer><button type="button" class="rateb-pos-v2__pay rateb-pos-v2__pay--sm" data-pos-receipt-close><?php echo __('close'); ?></button></footer>
        </div>
    </dialog>
</div>
