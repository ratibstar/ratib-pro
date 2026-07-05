<?php
declare(strict_types=1);

/** @var array<string, mixed> $shift */
$shift = $shift ?? [];
?>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
<div class="rateb-pos-page">
    <div class="rateb-card">
        <div class="rateb-card-header"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></div>
        <div class="rateb-card-body">
            <p class="text-muted small"><?php echo __('pos_shift_close_hint'); ?></p>
            <form method="post" action="<?php echo rateb_app_url('pos/shifts/' . (int) ($shift['id'] ?? 0) . '/close'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo \Rateb\App\Pos\Support\PosView::escape($csrf ?? ''); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="pos_closing_float"><?php echo __('pos_closing_float'); ?></label>
                        <input class="form-control" type="number" step="0.01" min="0" id="pos_closing_float" name="closing_float" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="pos_close_notes"><?php echo __('notes'); ?></label>
                        <textarea class="form-control" id="pos_close_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-warning"><?php echo __('pos_shift_close'); ?></button>
                    <a href="<?php echo rateb_app_url('pos/shifts/' . (int) ($shift['id'] ?? 0)); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
