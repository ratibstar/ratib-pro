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
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script type="application/json" id="rateb-pos-ui-labels"><?php echo $uiLabels; ?></script>
<div class="rateb-pos-register rateb-pos-register--premium" id="rateb-pos-register-main"
     data-pos-register data-pos-register--premium data-register-ready="<?php echo $registerReady ? '1' : '0'; ?>">
    <?php if (!$registerReady): ?>
    <div class="rateb-pos-alert rateb-pos-alert--warn" role="alert">
        <p><?php echo __('pos_no_shift_warning'); ?></p>
        <a class="btn btn-primary" href="<?php echo $shiftUrl; ?>"><?php echo __('pos_open_shift_link'); ?></a>
    </div>
    <?php endif; ?>

    <div class="rateb-pos-premium-layout">
        <div class="rateb-pos-premium-main">
            <div class="rateb-pos-premium-searchbar" role="search" aria-label="<?php echo __('pos_product_search'); ?>">
                <div class="rateb-pos-field rateb-pos-field--compact">
                    <label class="rateb-pos-label visually-hidden" for="rateb-pos-customer-input"><?php echo __('pos_customer'); ?></label>
                    <div class="rateb-pos-combobox" data-pos-customer-combobox>
                        <input type="search" id="rateb-pos-customer-input" class="form-control rateb-pos-input"
                               autocomplete="off" role="combobox" aria-expanded="false" aria-controls="rateb-pos-customer-list"
                               aria-autocomplete="list" placeholder="<?php echo __('pos_customer_search'); ?>"
                               data-pos-customer-input />
                        <button type="button" class="btn btn-outline-secondary rateb-pos-icon-btn" data-pos-customer-clear
                                aria-label="<?php echo __('pos_customer_clear'); ?>" title="<?php echo __('pos_customer_clear'); ?>">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                        <ul id="rateb-pos-customer-list" class="rateb-pos-combobox-list" role="listbox" hidden data-pos-customer-list></ul>
                    </div>
                </div>
                <div class="rateb-pos-field rateb-pos-field--compact">
                    <label class="rateb-pos-label visually-hidden" for="rateb-pos-search-input"><?php echo __('pos_product_search'); ?></label>
                    <div class="rateb-pos-combobox" data-pos-product-combobox>
                        <input type="search" id="rateb-pos-search-input" class="form-control rateb-pos-input"
                               autocomplete="off" role="combobox" aria-expanded="false" aria-controls="rateb-pos-product-list"
                               aria-autocomplete="list" placeholder="<?php echo __('pos_search_placeholder'); ?>"
                               data-pos-product-search />
                        <ul id="rateb-pos-product-list" class="rateb-pos-combobox-list" role="listbox" hidden data-pos-product-list></ul>
                    </div>
                </div>
                <div class="rateb-pos-field rateb-pos-field--compact">
                    <label class="rateb-pos-label visually-hidden" for="rateb-pos-barcode-input"><?php echo __('pos_barcode_scan'); ?></label>
                    <input type="text" id="rateb-pos-barcode-input" class="form-control rateb-pos-input rateb-pos-barcode-input"
                           inputmode="numeric" autocomplete="off" placeholder="<?php echo __('pos_barcode_placeholder'); ?>"
                           aria-describedby="rateb-pos-barcode-hint" data-pos-barcode-input />
                </div>
            </div>
            <p id="rateb-pos-barcode-hint" class="rateb-pos-hint rateb-pos-hint--inline"><?php echo __('pos_shortcut_barcode'); ?></p>

            <div class="rateb-pos-categories" data-pos-categories aria-label="<?php echo __('pos_categories'); ?>"></div>

            <div class="rateb-pos-product-grid" data-pos-product-grid role="grid"
                 aria-label="<?php echo __('pos_product_search'); ?>"></div>
        </div>

        <aside class="rateb-pos-premium-cart" aria-labelledby="rateb-pos-cart-title">
            <div class="rateb-pos-cart-header">
                <h2 id="rateb-pos-cart-title" class="rateb-pos-panel-title"><?php echo __('pos_cart'); ?></h2>
                <span class="rateb-pos-cart-count" data-pos-cart-count aria-live="polite">0</span>
            </div>

            <div class="rateb-pos-cart-lines" data-pos-cart-lines role="list" aria-label="<?php echo __('pos_cart'); ?>"></div>
            <p class="rateb-pos-cart-empty" data-pos-cart-empty><?php echo __('pos_cart_empty'); ?></p>

            <div class="rateb-pos-premium-customer" data-pos-customer-display-wrap>
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                <span data-pos-customer-display aria-live="polite"><?php echo __('pos_walk_in_customer'); ?></span>
            </div>

            <dl class="rateb-pos-totals-dl rateb-pos-premium-totals">
                <div><dt><?php echo __('pos_subtotal'); ?></dt><dd data-pos-subtotal>0.00</dd></div>
                <div><dt><?php echo __('pos_discount_total'); ?></dt><dd data-pos-discount-total>0.00</dd></div>
                <div><dt><?php echo __('pos_tax'); ?></dt><dd data-pos-tax>0.00</dd></div>
                <div class="rateb-pos-totals-grand"><dt><?php echo __('pos_total'); ?></dt><dd data-pos-total>0.00</dd></div>
            </dl>

            <div class="rateb-pos-actions rateb-pos-premium-actions" role="group" aria-label="<?php echo __('pos_actions'); ?>">
                <button type="button" class="btn btn-primary rateb-pos-action-btn" data-pos-checkout-open><?php echo __('pos_checkout'); ?></button>
                <button type="button" class="btn btn-outline-secondary rateb-pos-action-btn" data-pos-suspend><?php echo __('pos_suspend_sale'); ?></button>
                <button type="button" class="btn btn-outline-secondary rateb-pos-action-btn" data-pos-save-quote><?php echo __('pos_save_quote'); ?></button>
                <?php if (function_exists('rateb_can') && rateb_can('pos.returns.manage')): ?>
                    <button type="button" class="btn btn-outline-warning rateb-pos-action-btn" data-pos-return-open><?php echo __('pos_return'); ?></button>
                    <button type="button" class="btn btn-outline-info rateb-pos-action-btn" data-pos-exchange-open><?php echo __('pos_exchange'); ?></button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary rateb-pos-action-btn" data-pos-new-sale><?php echo __('pos_new_sale'); ?></button>
                <button type="button" class="btn btn-outline-danger rateb-pos-action-btn" data-pos-clear-cart><?php echo __('pos_clear_cart'); ?></button>
            </div>

            <label class="rateb-pos-gift-receipt">
                <input type="checkbox" data-pos-gift-receipt /> <?php echo __('pos_gift_receipt'); ?>
            </label>

            <div class="rateb-pos-ops-panel">
                <h3 class="rateb-pos-panel-title visually-hidden"><?php echo __('pos_register_ops'); ?></h3>
                <div class="rateb-pos-ops-list" data-pos-suspended-list aria-label="<?php echo __('pos_suspended_sales'); ?>"></div>
            </div>

            <?php if (function_exists('rateb_can') && rateb_can('pos.returns.manage')): ?>
            <div class="rateb-pos-return-panel" data-pos-return-panel hidden>
                <h3 class="rateb-pos-panel-title" data-pos-ops-panel-title><?php echo __('pos_return'); ?></h3>
                <div class="rateb-pos-field">
                    <label class="rateb-pos-label" for="rateb-pos-order-search"><?php echo __('pos_search_order'); ?></label>
                    <div class="rateb-pos-combobox" data-pos-order-combobox>
                        <input type="search" id="rateb-pos-order-search" class="form-control rateb-pos-input"
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
                    <select class="form-control rateb-pos-input" data-pos-return-refund-method>
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
                    <input type="text" class="form-control rateb-pos-input" data-pos-exchange-coupon maxlength="40" />
                    <label class="rateb-pos-label mt-2"><?php echo __('pos_loyalty_points'); ?></label>
                    <input type="number" min="0" step="0.01" class="form-control rateb-pos-input" data-pos-exchange-points value="0" />
                </div>
                <div data-pos-exchange-payments hidden>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" data-pos-exchange-add-payment><?php echo __('pos_add_payment'); ?></button>
                </div>
                <p class="rateb-pos-hint" data-pos-ops-net-summary hidden></p>
                <button type="button" class="btn btn-warning" data-pos-return-submit><?php echo __('pos_process_return'); ?></button>
                <button type="button" class="btn btn-info" data-pos-exchange-submit hidden><?php echo __('pos_process_exchange'); ?></button>
            </div>
            <?php endif; ?>

            <div class="rateb-pos-checkout-panel" data-pos-checkout-panel hidden>
                <h3 class="rateb-pos-panel-title"><?php echo __('pos_checkout'); ?></h3>
                <div class="rateb-pos-checkout-grid">
                    <div>
                        <label class="rateb-pos-label" for="rateb-pos-invoice-disc-type"><?php echo __('pos_invoice_discount'); ?></label>
                        <div class="rateb-pos-payment-row">
                            <select id="rateb-pos-invoice-disc-type" class="form-control rateb-pos-input" data-pos-invoice-discount-type>
                                <option value="amount"><?php echo __('pos_discount_amount'); ?></option>
                                <option value="percent"><?php echo __('pos_discount_percent'); ?></option>
                            </select>
                            <input type="number" min="0" step="0.01" class="form-control rateb-pos-input" data-pos-invoice-discount-value value="0" />
                        </div>
                    </div>
                    <div class="rateb-pos-rewards-row" data-pos-rewards-panel>
                        <div class="rateb-pos-field">
                            <label class="rateb-pos-label"><?php echo __('pos_coupon_code'); ?></label>
                            <div class="rateb-pos-payment-row">
                                <input type="text" class="form-control rateb-pos-input" data-pos-coupon-code maxlength="40" />
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-pos-apply-coupon><?php echo __('pos_apply_coupon'); ?></button>
                            </div>
                            <p class="rateb-pos-hint" data-pos-coupon-msg hidden></p>
                        </div>
                        <div class="rateb-pos-field">
                            <label class="rateb-pos-label"><?php echo __('pos_loyalty_points'); ?></label>
                            <input type="number" min="0" step="0.01" class="form-control rateb-pos-input" data-pos-points-redeem value="0" />
                            <p class="rateb-pos-hint" data-pos-loyalty-balance><?php echo __('pos_loyalty_balance'); ?>: —</p>
                        </div>
                    </div>
                    <dl class="rateb-pos-checkout-summary" data-pos-checkout-summary></dl>
                </div>
                <div data-pos-payment-list></div>
                <div class="rateb-pos-checkout-actions">
                    <button type="button" class="btn btn-outline-secondary" data-pos-add-payment><?php echo __('pos_add_payment'); ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-pos-checkout-close><?php echo __('cancel'); ?></button>
                    <button type="button" class="btn btn-success" data-pos-checkout-complete><?php echo __('pos_complete_sale'); ?></button>
                </div>
            </div>

            <p class="rateb-pos-selected-line" data-pos-selected-line aria-live="polite"></p>

            <details class="rateb-pos-shortcuts-help">
                <summary><?php echo __('pos_keyboard_shortcuts'); ?></summary>
                <ul class="rateb-pos-shortcuts-list" data-pos-shortcuts-list></ul>
            </details>
        </aside>
    </div>

    <div class="rateb-pos-status" role="status" aria-live="polite" aria-atomic="true" data-pos-status></div>

    <dialog class="rateb-pos-modal" id="rateb-pos-serial-modal" aria-labelledby="rateb-pos-serial-title" data-pos-serial-modal>
        <form method="dialog" class="rateb-pos-modal-inner">
            <header class="rateb-pos-modal-header">
                <h2 id="rateb-pos-serial-title"><?php echo __('pos_select_serial'); ?></h2>
                <button type="button" class="btn btn-sm btn-outline-secondary rateb-pos-modal-close" data-pos-serial-close aria-label="<?php echo __('close'); ?>">×</button>
            </header>
            <p class="rateb-pos-modal-product" data-pos-serial-product></p>
            <ul class="rateb-pos-serial-list" role="listbox" data-pos-serial-list></ul>
            <footer class="rateb-pos-modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-pos-serial-close><?php echo __('cancel'); ?></button>
            </footer>
        </form>
    </dialog>

    <dialog class="rateb-pos-modal rateb-pos-receipt-modal" id="rateb-pos-receipt-modal" aria-labelledby="rateb-pos-receipt-title">
        <div class="rateb-pos-modal-inner">
            <header class="rateb-pos-modal-header">
                <h2 id="rateb-pos-receipt-title"><?php echo __('pos_receipt'); ?></h2>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-pos-receipt-close aria-label="<?php echo __('close'); ?>">×</button>
            </header>
            <div class="rateb-pos-receipt-body" data-pos-receipt-body></div>
            <footer class="rateb-pos-modal-footer">
                <button type="button" class="btn btn-primary" data-pos-receipt-close><?php echo __('close'); ?></button>
            </footer>
        </div>
    </dialog>
</div>
