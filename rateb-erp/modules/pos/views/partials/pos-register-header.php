<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
$shift = $context['shift'] ?? null;
$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
$cashierLabel = (string) (\Rateb\App\Core\SessionManager::get('rateb_user_display') ?? \Rateb\App\Core\SessionManager::get('rateb_user_email') ?? '—');
?>
<header class="rateb-pos__header" role="banner">
    <div class="rateb-pos__header-meta">
        <span class="rateb-pos__header-item" title="<?php echo __('pos_cashier'); ?>">
            <svg class="rateb-pos__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></span>
        </span>
        <span class="rateb-pos__header-item" title="<?php echo __('pos_context_shift'); ?>">
            <svg class="rateb-pos__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?></span>
        </span>
    </div>

    <button type="button" class="rateb-pos__customer-pill" data-pos-focus-customer aria-label="<?php echo __('pos_customer'); ?>">
        <svg class="rateb-pos__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span data-pos-toolbar-customer><?php echo __('pos_walk_in_customer'); ?></span>
        <svg class="rateb-pos__icon rateb-pos__customer-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
    </button>

    <div class="rateb-pos__header-end">
        <time class="rateb-pos__clock" data-pos-clock datetime="" aria-live="off"></time>
        <span class="rateb-pos__connection" data-pos-connection-status role="status" aria-live="polite">
            <svg class="rateb-pos__icon rateb-pos__connection-dot" width="8" height="8" viewBox="0 0 8 8" fill="currentColor" aria-hidden="true"><circle cx="4" cy="4" r="4"/></svg>
            <span class="rateb-pos-connection__label"><?php echo __('pos_online'); ?></span>
        </span>
        <button type="button" class="rateb-pos__modes-trigger" data-pos-modes-toggle aria-expanded="false" aria-controls="rateb-pos-modes-menu" aria-label="<?php echo __('pos_more_actions'); ?>">
            <svg class="rateb-pos__icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
        </button>
    </div>
    <span class="visually-hidden" data-pos-toolbar-total aria-live="polite">0.00</span>
</header>
