<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Controllers\V2;

use Rateb\App\Core\Csrf;
use Rateb\App\Pos\Controllers\PosBaseController;

/** V2 register web shell (Sprint 3 / T52). */
final class PosV2RegisterController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');

        $this->posView('v2/register/index', [
            'title' => __('pos_register'),
            'v2Config' => $this->v2RegisterConfig(),
        ], 'pos-v2-shell');
    }

    /** @return array<string, mixed> */
    private function v2RegisterConfig(): array
    {
        return [
            'locale' => rateb_locale(),
            'rtl' => rateb_is_rtl(),
            'csrf' => Csrf::token(),
            'api' => [
                'bootstrap' => rateb_app_url('pos/api/v2/bootstrap'),
                'catalogSearch' => rateb_app_url('pos/api/v2/catalog/search'),
                'catalogProduct' => rateb_app_url('pos/api/v2/catalog/product'),
                'cartLines' => rateb_app_url('pos/api/v2/cart/lines'),
                'cartLine' => rateb_app_url('pos/api/v2/cart/lines'),
                'cartCustomer' => rateb_app_url('pos/api/v2/cart/customer'),
                'discountLine' => rateb_app_url('pos/api/v2/cart/discounts/line'),
                'discountCart' => rateb_app_url('pos/api/v2/cart/discounts/cart'),
            ],
            'localeUrls' => [
                'en' => function_exists('rateb_locale_switch_url') ? rateb_locale_switch_url('en') : '',
                'ar' => function_exists('rateb_locale_switch_url') ? rateb_locale_switch_url('ar') : '',
            ],
            'i18n' => $this->v2RegisterI18n(),
        ];
    }

    /** @return array<string, string> */
    private function v2RegisterI18n(): array
    {
        $keys = [
            'register_title' => __('pos_register'),
            'theme' => 'Theme',
            'theme_light' => __('pos_theme_light'),
            'theme_dark' => __('pos_theme_dark'),
            'language' => 'Language',
            'v1_link' => 'V1 Register',
            'search' => __('pos_product_search'),
            'search_placeholder' => __('pos_search_placeholder'),
            'clear' => __('clear'),
            'categories' => 'Categories',
            'all_categories' => __('pos_cat_all'),
            'loading' => __('pos_register_loading'),
            'loading_more' => 'Loading more…',
            'catalog_empty' => __('pos_catalog_empty'),
            'catalog_error' => 'Failed to load products.',
            'retry' => 'Retry',
            'products_count' => '{n} products',
            'in_stock' => __('pos_stock_available'),
            'out_of_stock' => __('pos_out_of_stock'),
            'weighted' => 'Weighted',
            'cart' => __('pos_cart'),
            'cart_empty' => __('pos_cart_empty'),
            'customer' => __('pos_customer'),
            'discount' => __('pos_invoice_discount'),
            'subtotal' => __('pos_subtotal'),
            'discount_total' => __('pos_discount_total'),
            'total' => __('pos_total'),
            'customer_hint' => 'Attach a customer by ID.',
            'customer_id' => 'Customer ID',
            'attach' => 'Attach',
            'remove' => __('pos_remove_line'),
            'discount_hint' => 'Apply percent or fixed discounts.',
            'cart_discount' => 'Cart discount',
            'line_discount' => 'Line discount',
            'type' => 'Type',
            'percent' => __('pos_discount_percent'),
            'fixed' => __('pos_discount_amount'),
            'value' => 'Value',
            'reason' => 'Reason (optional)',
            'apply_cart_discount' => 'Apply to cart',
            'apply_line_discount' => 'Apply to line',
            'line' => 'Cart line',
            'select_line' => __('pos_select_line'),
            'decrease' => __('pos_decrease_qty'),
            'increase' => __('pos_increase_qty'),
            'quantity' => __('pos_qty'),
            'line_discounted' => 'Discount applied',
            'added_to_cart' => __('pos_add_to_cart'),
            'cart_error' => __('pos_checkout_failed'),
            'customer_id_invalid' => 'Enter a valid customer ID.',
            'customer_attached' => 'Customer attached.',
            'customer_removed' => 'Customer removed.',
            'customer_error' => 'Customer operation failed.',
            'discount_value_required' => 'Enter a discount value.',
            'discount_applied' => 'Discount applied.',
            'discount_error' => 'Could not apply discount.',
            'select_line_required' => __('pos_select_line'),
            'bootstrap_error' => 'Failed to load register.',
            'fatal_error' => 'POS failed to start.',
        ];

        $out = [];
        foreach ($keys as $key => $label) {
            $out[$key] = $label;
        }

        return $out;
    }
}
