<?php
declare(strict_types=1);

use Rateb\App\GuestMenu\Support\GuestMenuView;

/** @var string $title */
/** @var array<string, mixed> $settings */
/** @var array<string, mixed> $catalog */
/** @var bool $rtl */
/** @var string $apiUrl */
/** @var string $orderApiUrl */
/** @var bool $orderMode */

$welcome = trim((string) ($settings['welcome_message'] ?? ''));
$categories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
$products = is_array($catalog['products'] ?? null) ? $catalog['products'] : [];
?>
<div class="gm-shell"
     data-gm-api="<?php echo GuestMenuView::escape($apiUrl); ?>"
     data-gm-order-api="<?php echo GuestMenuView::escape($orderApiUrl); ?>"
     data-gm-order-mode="<?php echo !empty($orderMode) ? '1' : '0'; ?>">
    <header class="gm-header">
        <h1 class="gm-title"><?php echo GuestMenuView::escape($title); ?></h1>
        <?php if ($welcome !== '') { ?>
        <p class="gm-welcome"><?php echo GuestMenuView::escape($welcome); ?></p>
        <?php } ?>
    </header>

    <nav class="gm-categories" aria-label="Categories">
        <button type="button" class="gm-cat is-active" data-category=""><?php echo __('guest_menu_all_categories'); ?></button>
        <?php foreach ($categories as $cat) { ?>
        <button type="button" class="gm-cat" data-category="<?php echo (int) ($cat['id'] ?? 0); ?>">
            <?php echo GuestMenuView::escape((string) ($cat['name'] ?? '')); ?>
        </button>
        <?php } ?>
    </nav>

    <main class="gm-grid" id="gm-product-grid">
        <?php foreach ($products as $product) {
            $inStock = !empty($product['in_stock']);
            $price = is_array($product['price'] ?? null) ? $product['price'] : [];
            $pid = (int) ($product['id'] ?? 0);
            ?>
        <article class="gm-card<?php echo $inStock ? '' : ' is-unavailable'; ?>"
                 data-product-id="<?php echo $pid; ?>"
                 data-product-name="<?php echo GuestMenuView::escape((string) ($product['name'] ?? '')); ?>"
                 data-product-price="<?php echo GuestMenuView::escape((string) ($price['amount'] ?? '0')); ?>"
                 data-product-currency="<?php echo GuestMenuView::escape((string) ($price['currency'] ?? 'SAR')); ?>">
            <?php if (!empty($product['image_url'])) { ?>
            <div class="gm-card__img" style="background-image:url('<?php echo GuestMenuView::escape((string) $product['image_url']); ?>')"></div>
            <?php } else { ?>
            <div class="gm-card__img gm-card__img--placeholder"></div>
            <?php } ?>
            <div class="gm-card__body">
                <h2 class="gm-card__name"><?php echo GuestMenuView::escape((string) ($product['name'] ?? '')); ?></h2>
                <p class="gm-card__price">
                    <?php echo GuestMenuView::escape(number_format((float) ($price['amount'] ?? 0), 2)); ?>
                    <?php echo GuestMenuView::escape((string) ($price['currency'] ?? 'SAR')); ?>
                </p>
                <?php if (!$inStock) { ?>
                <span class="gm-badge"><?php echo __('guest_menu_out_of_stock'); ?></span>
                <?php } elseif (!empty($orderMode)) { ?>
                <button type="button" class="gm-add-btn" data-gm-add><?php echo __('guest_menu_add_to_cart'); ?></button>
                <?php } ?>
            </div>
        </article>
        <?php } ?>
    </main>
    <?php if ($products === []) { ?>
    <p class="gm-empty" id="gm-empty-msg"><?php echo __('guest_menu_no_products'); ?></p>
    <?php } ?>

    <?php if (!empty($orderMode)) { ?>
    <aside class="gm-cart" id="gm-cart" hidden>
        <div class="gm-cart__head">
            <strong><?php echo __('guest_menu_cart'); ?></strong>
            <button type="button" class="gm-cart__close" id="gm-cart-close" aria-label="Close">&times;</button>
        </div>
        <div class="gm-cart__fields">
            <input class="form-control form-control-sm mb-2" type="text" id="gm-table-label" placeholder="<?php echo GuestMenuView::escape(__('guest_menu_table_label')); ?>">
            <input class="form-control form-control-sm mb-2" type="text" id="gm-guest-name" placeholder="<?php echo GuestMenuView::escape(__('guest_menu_guest_name')); ?>">
        </div>
        <ul class="gm-cart__list" id="gm-cart-list"></ul>
        <div class="gm-cart__foot">
            <div class="gm-cart__total"><span><?php echo __('guest_menu_total'); ?>:</span> <strong id="gm-cart-total">0.00</strong></div>
            <button type="button" class="gm-cart__submit" id="gm-cart-submit"><?php echo __('guest_menu_submit_order'); ?></button>
        </div>
        <p class="gm-cart__msg" id="gm-cart-msg" hidden></p>
    </aside>
    <button type="button" class="gm-cart-fab" id="gm-cart-fab" hidden>
        <?php echo __('guest_menu_cart'); ?> (<span id="gm-cart-count">0</span>)
    </button>
    <?php } else { ?>
    <footer class="gm-footer">
        <p><?php echo __('guest_menu_powered_by'); ?></p>
    </footer>
    <?php } ?>
</div>
