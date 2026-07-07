<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Services\PosRegisterCartService;
use Rateb\App\Pos\Services\PosSessionService;

final class PosRegisterController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');

        $companyId = $this->companyId();
        $userId = $this->userId();
        $contextService = new PosContextService();
        $contextService->syncRegisterFromOpenShift($companyId, $userId);

        $session = new PosSessionService();
        $cart = new PosRegisterCartService();
        $lines = $cart->normalizeLines($session->getCartLines());
        $context = $contextService->snapshot();

        $this->posView('register/index', [
            'title' => __('pos_register'),
            'context' => $context,
            'session' => $session->snapshot(),
            'totals' => $cart->totals($lines),
            'csrf' => Csrf::token(),
            'registerConfig' => $this->registerConfig($context, $session->snapshot(), $lines),
        ], 'pos-shell');
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $session @param array<int, array<string, mixed>> $lines */
    private function registerConfig(array $context, array $session, array $lines): array
    {
        return [
            'locale' => rateb_locale(),
            'rtl' => rateb_is_rtl(),
            'csrf' => Csrf::token(),
            'companyId' => $this->companyId(),
            'userId' => $this->userId(),
            'api' => [
                'session' => rateb_app_url('pos/api/register/session'),
                'sessionSave' => rateb_app_url('pos/api/register/session'),
                'customers' => rateb_app_url('pos/api/register/customers/search'),
                'products' => rateb_app_url('pos/api/register/products/search'),
                'barcode' => rateb_app_url('pos/api/register/barcode'),
                'pricing' => rateb_app_url('pos/api/register/pricing'),
                'cartAdd' => rateb_app_url('pos/api/register/cart/add'),
                'cartUpdate' => rateb_app_url('pos/api/register/cart/update-line'),
                'productDetail' => rateb_app_url('pos/api/register/products/detail'),
                'productAvailability' => rateb_app_url('pos/api/register/products/availability'),
                'fefoPreview' => rateb_app_url('pos/api/register/products/fefo-preview'),
                'productSerials' => rateb_app_url('pos/api/register/products/serials'),
                'checkout' => rateb_app_url('pos/api/register/checkout'),
                'validateCoupon' => rateb_app_url('pos/api/register/coupon/validate'),
                'validateGiftCard' => rateb_app_url('pos/api/register/gift-card/validate'),
                'loyaltyBalance' => rateb_app_url('pos/api/register/loyalty/balance'),
                'suspend' => rateb_app_url('pos/api/register/suspend'),
                'suspendedList' => rateb_app_url('pos/api/register/suspended'),
                'suspendedResume' => rateb_app_url('pos/api/register/suspended/{id}/resume'),
                'quoteSave' => rateb_app_url('pos/api/register/quote/save'),
                'quotesList' => rateb_app_url('pos/api/register/quotes'),
                'returnableLines' => rateb_app_url('pos/api/register/orders/{id}/returnable-lines'),
                'searchOrders' => rateb_app_url('pos/api/register/orders/search'),
                'processReturn' => rateb_app_url('pos/api/register/return'),
                'processExchange' => rateb_app_url('pos/api/register/exchange'),
                'sync' => rateb_app_url('pos/api/sync'),
            ],
            'canReturns' => function_exists('rateb_can') && rateb_can('pos.returns.manage'),
            'serviceWorker' => rateb_public_url('pos-sw.js'),
            'serviceWorkerScope' => rateb_public_url(''),
            'context' => $context,
            'session' => $session,
            'initialLines' => $lines,
            'i18n' => $this->registerI18n(),
        ];
    }

    /** @return array<string, string> */
    private function registerI18n(): array
    {
        $keys = [
            'pos_cart', 'pos_cart_empty', 'pos_customer', 'pos_customer_search', 'pos_customer_clear',
            'pos_product_search', 'pos_barcode_scan', 'pos_barcode_placeholder', 'pos_search_placeholder',
            'pos_qty', 'pos_unit_price', 'pos_line_total', 'pos_subtotal', 'pos_tax', 'pos_total',
            'pos_clear_cart', 'pos_hold', 'pos_cancel_order', 'pos_new_sale', 'pos_remove_line', 'pos_increase_qty', 'pos_decrease_qty',
            'pos_select_line', 'pos_no_shift_warning', 'pos_open_shift_link', 'pos_product_not_found',
            'pos_search_no_results', 'pos_catalog_empty', 'pos_catalog_empty_hint', 'pos_cat_all', 'pos_checkout_disabled', 'pos_session_saved', 'pos_theme_dark',
            'pos_theme_light', 'pos_keyboard_shortcuts', 'pos_shortcut_search', 'pos_shortcut_barcode',
            'pos_shortcut_customer', 'pos_shortcut_clear', 'pos_shortcut_qty_up', 'pos_shortcut_qty_down',
            'pos_item_code', 'pos_item_name', 'pos_actions', 'pos_register_ready', 'pos_register_loading',
            'pos_walk_in_customer', 'pos_add_to_cart', 'pos_selected_line', 'pos_api_ready_note',
            'pos_clear_selection', 'pos_skip_to_register',
            'pos_stock_available', 'pos_stock_on_hand', 'pos_stock_reserved', 'pos_insufficient_stock',
            'pos_serial_required', 'pos_serial_select', 'pos_serial_unavailable', 'pos_serial_duplicate',
            'pos_serial_qty_one', 'pos_fefo_preview', 'pos_batch_no', 'pos_expiry_date', 'pos_select_serial',
            'pos_out_of_stock', 'pos_line_stock',
            'pos_checkout', 'pos_complete_sale', 'pos_receipt', 'pos_invoice_discount',
            'pos_discount_amount', 'pos_discount_percent', 'pos_discount_total',
            'pos_payment_method', 'pos_payment_amount', 'pos_payment_reference', 'pos_add_payment',
            'pos_payment_invalid_method', 'pos_payment_invalid_amount',             'pos_payment_mismatch', 'pos_checkout_failed',
            'pos_register_ops', 'pos_suspend_sale', 'pos_save_quote', 'pos_gift_receipt',
            'pos_return', 'pos_suspended_sales', 'pos_original_order', 'pos_line_id',
            'pos_refund_method', 'pos_refund_cash', 'pos_refund_card', 'pos_refund_bank',
            'pos_refund_wallet', 'pos_refund_store_credit', 'pos_process_return',
            'pos_suspend_saved', 'pos_quote_saved',             'pos_return_complete', 'pos_order_type',
            'pos_coupon_code', 'pos_apply_coupon', 'pos_loyalty_points', 'pos_loyalty_balance',
            'pos_gift_card_code', 'pos_gift_card_balance', 'pos_refund_gift_card',
            'pos_exchange', 'pos_process_exchange', 'pos_search_order', 'pos_search_order_placeholder',
            'pos_return_lines', 'pos_returnable_qty', 'pos_exchange_cart_hint',
            'pos_net_due', 'pos_net_refund', 'pos_net_even', 'pos_exchange_complete',
            'invalid_request', 'pos_discount_permission_denied', 'pos_offline_queued', 'pos_offline_mode_banner',
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = __($key);
        }
        return $out;
    }
}
