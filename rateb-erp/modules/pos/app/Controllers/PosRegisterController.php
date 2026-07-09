<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Services\PosRegisterCartService;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Services\BiometricAuthService;

final class PosRegisterController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');

        $userId = $this->userId();
        $bio = new BiometricAuthService();
        if (!$bio->isPosVerified($userId)) {
            $this->redirect(rateb_app_url('pos/biometric'));
            return;
        }

        $companyId = $this->companyId();
        $contextService = new PosContextService();
        $contextService->syncRegisterFromOpenShift($companyId, $userId);

        $session = new PosSessionService();
        $cart = new PosRegisterCartService();
        $lines = $cart->normalizeLines($session->getCartLines());
        $context = $contextService->snapshot();
        $totals = $cart->totals($lines);
        $registerConfig = $this->registerConfig($context, $session->snapshot(), $lines, $totals);

        $this->posView('register/index', [
            'title' => __('pos_register'),
            'context' => $context,
            'session' => $session->snapshot(),
            'totals' => $totals,
            'csrf' => Csrf::token(),
            'capabilities' => $registerConfig['capabilities'] ?? [],
            'registerConfig' => $registerConfig,
        ], 'pos-shell');
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $session @param array<int, array<string, mixed>> $lines @param array<string, mixed> $totals */
    private function registerConfig(array $context, array $session, array $lines, array $totals): array
    {
        $shift = is_array($context['shift'] ?? null) ? $context['shift'] : [];
        $shiftId = (int) ($shift['id'] ?? 0);
        $capabilities = $this->resolveCapabilities();

        return [
            'locale' => rateb_locale(),
            'rtl' => rateb_is_rtl(),
            'csrf' => Csrf::token(),
            'companyId' => $this->companyId(),
            'userId' => $this->userId(),
            'shiftId' => $shiftId,
            'capabilities' => $capabilities,
            'api' => [
                'bootstrap' => rateb_app_url('pos/api/register/bootstrap'),
                'session' => rateb_app_url('pos/api/register/session'),
                'sessionSave' => rateb_app_url('pos/api/register/session'),
                'customers' => rateb_app_url('pos/api/register/customers/search'),
                'customerCreate' => rateb_app_url('pos/api/register/customers/create'),
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
                'drawerEvent' => rateb_app_url('pos/api/register/drawer/event'),
                'drawerOpen' => rateb_app_url('pos/api/register/drawer/open'),
                'xReport' => rateb_app_url('pos/api/register/reports/x'),
                'lastReceipt' => rateb_app_url('pos/api/register/receipt/last'),
                'suspend' => rateb_app_url('pos/api/register/suspend'),
                'suspendedList' => rateb_app_url('pos/api/register/suspended'),
                'suspendedResume' => rateb_app_url('pos/api/register/suspended/{id}/resume'),
                'quoteSave' => rateb_app_url('pos/api/register/quote/save'),
                'quotesList' => rateb_app_url('pos/api/register/quotes'),
                'quoteResume' => rateb_app_url('pos/api/register/quotes/{id}/resume'),
                'returnableLines' => rateb_app_url('pos/api/register/orders/{id}/returnable-lines'),
                'searchOrders' => rateb_app_url('pos/api/register/orders/search'),
                'processReturn' => rateb_app_url('pos/api/register/return'),
                'processExchange' => rateb_app_url('pos/api/register/exchange'),
                'sync' => rateb_app_url('pos/api/sync'),
                'approvalRequest' => rateb_app_url('pos/api/approval/request'),
                'approvalGrant' => rateb_app_url('pos/api/approval/grant'),
                'inventoryAdjust' => rateb_app_url('pos/api/inventory/adjust'),
                'biometricStart' => rateb_app_url('pos/api/biometric/start'),
                'biometricFinish' => rateb_app_url('pos/api/biometric/finish'),
                'biometricFace' => rateb_app_url('pos/api/biometric/face'),
                'biometricStatus' => rateb_app_url('pos/api/biometric/status'),
            ],
            'urls' => [
                'shiftClose' => $shiftId > 0 ? rateb_app_url('pos/shifts/' . $shiftId . '/close') : '',
                'register' => rateb_app_url('pos/register'),
            ],
            'canReturns' => $capabilities['returns'] ?? false,
            'canDiscount' => $capabilities['discounts'] ?? false,
            'canShiftClose' => $capabilities['shiftClose'] ?? false,
            'canDrawerManage' => $capabilities['drawerManage'] ?? false,
            'canPaymentCard' => $capabilities['paymentCard'] ?? false,
            'canInventoryAdjust' => $capabilities['inventoryAdjust'] ?? false,
            'serviceWorker' => rateb_public_url('pos-sw.js'),
            // Must stay under /rateb-erp/public/ (SW script location); rateb_public_url('') is site root on rateb.sa.
            'serviceWorkerScope' => rateb_site_origin() . rtrim(rateb_erp_app_prefix(), '/') . '/',
            'session' => $session,
            'registerScope' => [
                'terminal_id' => (int) (($context['terminal']['id'] ?? 0) ?: ($session['terminal_id'] ?? 0)),
                'shift_id' => $shiftId,
                'branch_id' => (int) (($context['branch']['id'] ?? 0) ?: ($session['branch_id'] ?? 0)),
                'warehouse_id' => (int) (($context['warehouse']['id'] ?? 0) ?: ($session['warehouse_id'] ?? 0)),
            ],
            'initialLines' => $lines,
            'initialTotals' => $totals,
            'i18n' => $this->registerI18n(),
        ];
    }

    /** @return array<string, mixed> */
    private function resolveCapabilities(): array
    {
        $can = static function (string $slug): bool {
            if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
                return true;
            }

            return function_exists('rateb_can') && rateb_can($slug);
        };

        return [
            'register' => $can('pos.register'),
            'returns' => $can('pos.returns.manage'),
            'discounts' => $can('pos.discount.manage'),
            'shiftClose' => $can('pos.shift.close'),
            'reports' => $can('pos.reports.view'),
            'settings' => $can('pos.settings.manage'),
            'inventoryAdjust' => $can('pos.inventory.adjust'),
            'supervisorApprove' => $can('pos.supervisor.approve'),
            'drawerManage' => $can('pos.cash_drawer.manage'),
            'paymentCard' => $can('pos.payment.record'),
            'nav' => [
                'register' => $can('pos.register'),
                'customers' => $can('pos.register'),
                'products' => $can('pos.register'),
                'inventory' => $can('pos.inventory.adjust') || $can('pos.view'),
                'purchases' => $can('pos.view'),
                'reports' => $can('pos.reports.view'),
                'settings' => $can('pos.settings.manage'),
            ],
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
            'pos_payment_invalid_method', 'pos_payment_invalid_amount', 'pos_payment_mismatch', 'pos_checkout_failed',
            'pos_register_ops', 'pos_suspend_sale', 'pos_save_quote', 'pos_gift_receipt',
            'pos_return', 'pos_suspended_sales', 'pos_original_order', 'pos_line_id',
            'pos_refund_method', 'pos_refund_cash', 'pos_refund_card', 'pos_refund_bank',
            'pos_refund_wallet', 'pos_refund_store_credit', 'pos_process_return',
            'pos_suspend_saved', 'pos_quote_saved', 'pos_return_complete', 'pos_order_type',
            'pos_saved_orders', 'pos_quotes', 'pos_suspended_empty', 'pos_quotes_empty',
            'pos_resume_sale', 'pos_load_quote', 'pos_quote_loaded',
            'pos_coupon_code', 'pos_apply_coupon', 'pos_loyalty_points', 'pos_loyalty_balance',
            'pos_gift_card_code', 'pos_gift_card_balance', 'pos_refund_gift_card', 'pos_gift_card_invalid',
            'pos_exchange', 'pos_process_exchange', 'pos_search_order', 'pos_search_order_placeholder',
            'pos_return_lines', 'pos_returnable_qty', 'pos_exchange_cart_hint',
            'pos_net_due', 'pos_net_refund', 'pos_net_even', 'pos_exchange_complete',
            'invalid_request', 'pos_discount_permission_denied', 'pos_offline_queued', 'pos_offline_mode_banner',
            'pos_x_report', 'pos_x_report_hint', 'pos_shift_close', 'pos_shift_close_hint', 'pos_closing_float',
            'pos_pay_in', 'pos_pay_out', 'pos_no_sale', 'pos_open_drawer', 'pos_cashier_tools',
            'pos_line_discount', 'pos_apply_line_discount', 'pos_confirm_clear_cart', 'pos_customer_quick_add',
            'pos_customer_name', 'pos_customer_phone', 'pos_add_customer', 'pos_print_receipt', 'pos_reprint_last',
            'pos_offline_queue', 'pos_gift_card_validate', 'pos_coupon_invalid', 'notes', 'close', 'saved',
            'pos_nav_sales', 'pos_nav_customers', 'pos_nav_products', 'pos_nav_inventory', 'pos_nav_purchases',
            'pos_pay_cash', 'pos_pay_card', 'pos_pay_other', 'pos_main_branch', 'pos_notifications', 'pos_fullscreen',
            'pos_shift_status_open', 'pos_shift_started', 'pos_shift_total_sales', 'pos_stock_adjust', 'pos_stock_adjust_qty',
            'pos_supervisor_approval', 'pos_supervisor_scan_prompt', 'pos_supervisor_scan_fingerprint',
            'pos_supervisor_approval_required', 'pos_biometric_gate', 'pos_biometric_scan_fingerprint',
            'pos_biometric_scan_face', 'pos_biometric_success', 'pos_biometric_failed', 'pos_biometric_not_enrolled',
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = __($key);
        }

        return $out;
    }
}
