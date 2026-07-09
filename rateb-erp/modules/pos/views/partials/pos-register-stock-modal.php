<?php
declare(strict_types=1);
?>
<div class="rateb-pos__modal rateb-pos__stock-modal" data-pos-stock-modal hidden role="dialog" aria-modal="true" aria-labelledby="rateb-pos-stock-title">
    <div class="rateb-pos__modal-backdrop" data-pos-stock-close tabindex="-1" aria-hidden="true"></div>
    <div class="rateb-pos__modal-panel">
        <header class="rateb-pos__modal-head">
            <h2 id="rateb-pos-stock-title"><?php echo __('pos_stock_adjust'); ?></h2>
            <button type="button" class="rateb-pos__modal-close" data-pos-stock-close aria-label="<?php echo __('close'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </header>
        <div class="rateb-pos__modal-body">
            <label class="rateb-pos__field-label" for="rateb-pos-stock-product"><?php echo __('pos_item_name'); ?></label>
            <input type="text" class="rateb-pos__input rateb-pos__input--block" id="rateb-pos-stock-product" data-pos-stock-product readonly />
            <label class="rateb-pos__field-label" for="rateb-pos-stock-sku"><?php echo __('pos_item_code'); ?></label>
            <input type="text" class="rateb-pos__input rateb-pos__input--block" id="rateb-pos-stock-sku" data-pos-stock-sku readonly />
            <label class="rateb-pos__field-label" for="rateb-pos-stock-qty"><?php echo __('pos_stock_adjust_qty'); ?></label>
            <input type="number" step="0.01" class="rateb-pos__input rateb-pos__input--block" id="rateb-pos-stock-qty" data-pos-stock-qty inputmode="decimal" />
            <label class="rateb-pos__field-label" for="rateb-pos-stock-reason"><?php echo __('notes'); ?></label>
            <input type="text" class="rateb-pos__input rateb-pos__input--block" id="rateb-pos-stock-reason" data-pos-stock-reason maxlength="200" />
            <input type="hidden" data-pos-stock-product-id value="" />
            <button type="button" class="rateb-pos__charge rateb-pos__charge--sm" data-pos-stock-save style="margin-top:12px;width:100%;">
                <?php echo __('saved'); ?>
            </button>
        </div>
    </div>
</div>
