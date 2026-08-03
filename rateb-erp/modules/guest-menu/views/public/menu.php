<?php
declare(strict_types=1);

use Rateb\App\GuestMenu\Support\GuestMenuView;

/** @var string $title */
/** @var array<string, mixed> $settings */
/** @var array<string, mixed> $catalog */
/** @var bool $rtl */
/** @var string $apiUrl */

$welcome = trim((string) ($settings['welcome_message'] ?? ''));
$categories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
$products = is_array($catalog['products'] ?? null) ? $catalog['products'] : [];
$mode = (string) ($settings['mode'] ?? 'browse');
?>
<div class="gm-shell" data-gm-api="<?php echo GuestMenuView::escape($apiUrl); ?>">
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
            ?>
        <article class="gm-card<?php echo $inStock ? '' : ' is-unavailable'; ?>">
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
                <?php } ?>
            </div>
        </article>
        <?php } ?>
    </main>

    <?php if ($mode === 'order') { ?>
    <footer class="gm-footer gm-footer--order">
        <p><?php echo __('guest_menu_order_soon'); ?></p>
    </footer>
    <?php } else { ?>
    <footer class="gm-footer">
        <p><?php echo __('guest_menu_powered_by'); ?></p>
    </footer>
    <?php } ?>
</div>
