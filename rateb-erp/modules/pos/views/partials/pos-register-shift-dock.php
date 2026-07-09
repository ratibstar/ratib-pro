<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var array<string, mixed>|null $registerConfig */
$shift = is_array($context['shift'] ?? null) ? $context['shift'] : null;
$shiftOpen = $shift !== null && (int) ($shift['id'] ?? 0) > 0;
$capabilities = is_array($registerConfig['capabilities'] ?? null) ? $registerConfig['capabilities'] : [];
$canShiftClose = !empty($capabilities['shiftClose']) || !empty($registerConfig['canShiftClose']);
$cashierLabel = (string) (\Rateb\App\Core\SessionManager::get('rateb_user_display') ?? \Rateb\App\Core\SessionManager::get('rateb_user_email') ?? '—');
$shiftNo = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
$openedAt = $shift ? (string) ($shift['opened_at'] ?? $shift['open_time'] ?? '—') : '—';
$openingFloat = $shift ? number_format((float) ($shift['opening_float'] ?? 0), 2) : '0.00';
$shiftCloseUrl = (string) ($registerConfig['urls']['shiftClose'] ?? '');
?>
<aside class="rateb-pos__shift-dock" data-pos-shift-dock aria-label="<?php echo __('pos_context_shift'); ?>" <?php echo $shiftOpen ? '' : 'hidden'; ?>>
    <header class="rateb-pos__shift-dock-head">
        <span class="rateb-pos__shift-dock-badge<?php echo $shiftOpen ? '' : ' is-closed'; ?>" data-pos-shift-status>
            <?php echo $shiftOpen ? __('pos_shift_status_open') : __('pos_shift_not_open'); ?>
        </span>
    </header>
    <dl class="rateb-pos__shift-dock-rows">
        <div class="rateb-pos__shift-dock-row">
            <dt><?php echo __('pos_shift_no'); ?></dt>
            <dd data-pos-shift-no><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftNo); ?></dd>
        </div>
        <div class="rateb-pos__shift-dock-row">
            <dt><?php echo __('pos_cashier'); ?></dt>
            <dd data-pos-shift-cashier><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></dd>
        </div>
        <div class="rateb-pos__shift-dock-row">
            <dt><?php echo __('pos_shift_started'); ?></dt>
            <dd data-pos-shift-started><?php echo \Rateb\App\Pos\Support\PosView::escape($openedAt); ?></dd>
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
    <?php if ($canShiftClose && $shiftCloseUrl !== ''): ?>
    <a class="rateb-pos__shift-dock-close" href="<?php echo \Rateb\App\Pos\Support\PosView::escape($shiftCloseUrl); ?>" data-pos-shift-close>
        <?php echo __('pos_shift_close'); ?>
    </a>
    <?php else: ?>
    <button type="button" class="rateb-pos__shift-dock-close" data-pos-shift-close hidden disabled>
        <?php echo __('pos_shift_close'); ?>
    </button>
    <?php endif; ?>
</aside>
