<?php
/** @var float $value */
/** @var string $currency */
/** @var bool $manual */
/** @var string $fieldName */
$value = (float) ($value ?? 0);
$currency = (string) ($currency ?? 'SAR');
$manual = !empty($manual);
$fieldName = (string) ($fieldName ?? 'total_estimated');
?>
<div class="col-12" data-procurement-estimated-total>
    <div class="rateb-card border-secondary">
        <div class="rateb-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="fas fa-calculator me-1"></i> <?php echo __('estimated_total'); ?></span>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="total_estimated_manual"
                       name="total_estimated_manual" value="1"
                       data-total-estimated-manual<?php echo $manual ? ' checked' : ''; ?>>
                <label class="form-check-label" for="total_estimated_manual"><?php echo __('estimated_total_manual'); ?></label>
            </div>
        </div>
        <div class="rateb-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5 col-lg-4">
                    <label class="form-label rateb-form-label" for="f_<?php echo Rateb\App\Core\View::escape($fieldName); ?>">
                        <?php echo __('estimated_total'); ?>
                        <span class="text-muted fw-normal" data-estimated-total-currency>(<?php echo Rateb\App\Core\View::escape($currency); ?>)</span>
                    </label>
                    <input class="form-control rateb-form-control rateb-ltr-num" type="number" step="0.01" min="0"
                           id="f_<?php echo Rateb\App\Core\View::escape($fieldName); ?>"
                           name="<?php echo Rateb\App\Core\View::escape($fieldName); ?>"
                           value="<?php echo Rateb\App\Core\View::escape(number_format($value, 2, '.', '')); ?>"
                           data-procurement-total-field<?php echo $manual ? '' : ' readonly'; ?>>
                </div>
                <div class="col-md-7 col-lg-8">
                    <p class="text-muted small mb-1" data-estimated-total-hint-auto<?php echo $manual ? ' style="display:none"' : ''; ?>>
                        <i class="fas fa-sync-alt me-1"></i> <?php echo __('estimated_total_auto_hint'); ?>
                    </p>
                    <p class="text-muted small mb-0" data-estimated-total-hint-manual<?php echo $manual ? '' : ' style="display:none"'; ?>>
                        <?php echo __('estimated_total_manual_hint'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
