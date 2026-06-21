<?php
/** @var string $currency */
/** @var float $discount */
/** @var float $shipping */
/** @var float $customs */
$currency = (string) ($currency ?? 'SAR');
$discount = (float) ($discount ?? 0);
$shipping = (float) ($shipping ?? 0);
$customs = (float) ($customs ?? 0);
?>
<div class="col-12">
    <div class="rateb-card border-primary">
        <div class="rateb-card-header bg-primary bg-opacity-10">
            <i class="fas fa-calculator me-1"></i> <?php echo __('procurement_summary'); ?>
        </div>
        <div class="rateb-card-body">
            <div class="row g-3 text-center">
                <div class="col-md-3 col-6">
                    <div class="text-muted small"><?php echo __('currency'); ?></div>
                    <div class="fw-bold" data-summary-currency><?php echo Rateb\App\Core\View::escape($currency); ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-muted small"><?php echo __('subtotal'); ?></div>
                    <div class="fw-bold"><span data-summary-subtotal>0.00</span> <span data-summary-currency-suffix><?php echo Rateb\App\Core\View::escape($currency); ?></span></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-muted small"><?php echo __('vat_15'); ?></div>
                    <div class="fw-bold text-warning"><span data-summary-tax>0.00</span> <span data-summary-currency-suffix2><?php echo Rateb\App\Core\View::escape($currency); ?></span></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-muted small"><?php echo __('discount'); ?></div>
                    <div class="fw-bold text-danger">-<span data-summary-discount><?php echo number_format($discount, 2); ?></span> <span data-summary-currency-suffix3><?php echo Rateb\App\Core\View::escape($currency); ?></span></div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="text-muted small"><?php echo __('shipping'); ?></div>
                    <div class="fw-bold"><span data-summary-shipping><?php echo number_format($shipping, 2); ?></span> <span data-summary-currency-suffix5><?php echo Rateb\App\Core\View::escape($currency); ?></span></div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="text-muted small"><?php echo __('customs_clearance_costs'); ?></div>
                    <div class="fw-bold"><span data-summary-customs><?php echo number_format($customs, 2); ?></span> <span data-summary-currency-suffix6><?php echo Rateb\App\Core\View::escape($currency); ?></span></div>
                </div>
            </div>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <span class="fs-5 fw-bold"><?php echo __('grand_total'); ?></span>
                <span class="fs-4 fw-bold text-primary"><span data-summary-grand>0.00</span> <span data-summary-currency-suffix4><?php echo Rateb\App\Core\View::escape($currency); ?></span></span>
            </div>
        </div>
    </div>
</div>
