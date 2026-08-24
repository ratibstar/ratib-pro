<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var array<string, mixed> $capabilities */
$shift = is_array($context['shift'] ?? null) ? $context['shift'] : null;
$shiftOpen = $shift !== null;
$cashierLabel = (string) (\Rateb\App\Core\SessionManager::get('rateb_user_display')
    ?? \Rateb\App\Core\SessionManager::get('rateb_user_email') ?? '—');
$openingFloat = $shift ? number_format((float) ($shift['opening_float'] ?? 0), 2) : '0.00';
$shiftStarted = $shift ? (string) ($shift['opened_at'] ?? $shift['created_at'] ?? '—') : '—';
$canClose = !empty($capabilities['shiftClose'] ?? false);
$shiftCloseUrl = '';
if ($shift && !empty($shift['id'])) {
    $shiftCloseUrl = rateb_app_url('pos/shifts/' . (int) $shift['id'] . '/close');
}
?>
<details class="rateb-pos__shift-dock" data-pos-shift-dock aria-label="<?php echo __('pos_shifts'); ?>">
    <summary class="rateb-pos__shift-dock-summary">
        <span class="rateb-pos__shift-dock-badge<?php echo $shiftOpen ? '' : ' is-closed'; ?>" data-pos-shift-status>
            <?php echo $shiftOpen ? __('pos_shift_open_status') : __('pos_shift_not_open'); ?>
        </span>
        <span class="rateb-pos__shift-dock-cashier"><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></span>
        <?php if ($canClose && $shiftCloseUrl !== ''): ?>
        <a class="rateb-pos__shift-dock-close rateb-pos__shift-dock-close--inline" href="<?php echo \Rateb\App\Pos\Support\PosView::escape($shiftCloseUrl); ?>" data-pos-shift-close-link onclick="event.stopPropagation()">
            <?php echo __('pos_shift_close'); ?>
        </a>
        <?php endif; ?>
    </summary>
    <div class="rateb-pos__shift-dock-body">
        <dl class="rateb-pos__shift-dock-rows">
            <div class="rateb-pos__shift-dock-row">
                <dt><?php echo __('pos_cashier'); ?></dt>
                <dd><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></dd>
            </div>
            <div class="rateb-pos__shift-dock-row">
                <dt><?php echo __('pos_shift_open'); ?></dt>
                <dd data-pos-shift-started><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftStarted); ?></dd>
            </div>
            <div class="rateb-pos__shift-dock-row">
                <dt><?php echo __('pos_opening_float'); ?></dt>
                <dd data-pos-shift-float><?php echo \Rateb\App\Pos\Support\PosView::escape($openingFloat); ?></dd>
            </div>
            <div class="rateb-pos__shift-dock-row">
                <dt><?php echo __('pos_shift_total_sales'); ?></dt>
                <dd data-pos-shift-sales>0.00</dd>
            </div>
        </dl>
        <?php if (!($canClose && $shiftCloseUrl !== '')): ?>
        <button type="button" class="rateb-pos__shift-dock-close" data-pos-shift-close-link hidden disabled>
            <?php echo __('pos_shift_close'); ?>
        </button>
        <?php endif; ?>
    </div>
</details>
