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
            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></span>
        </span>
        <span class="rateb-pos__header-item" title="<?php echo __('pos_context_shift'); ?>">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?></span>
        </span>
    </div>

    <button type="button" class="rateb-pos__customer-pill" data-pos-focus-customer aria-label="<?php echo __('pos_customer'); ?>">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <span data-pos-toolbar-customer><?php echo __('pos_walk_in_customer'); ?></span>
        <i class="fa-solid fa-chevron-down rateb-pos__customer-caret" aria-hidden="true"></i>
    </button>

    <div class="rateb-pos__header-end">
        <time class="rateb-pos__clock" data-pos-clock datetime="" aria-live="off"></time>
        <span class="rateb-pos__connection" data-pos-connection-status role="status" aria-live="polite">
            <i class="fa-solid fa-circle" aria-hidden="true"></i>
            <span class="rateb-pos-connection__label"><?php echo __('pos_online'); ?></span>
        </span>
        <button type="button" class="rateb-pos__modes-trigger" data-pos-modes-toggle aria-expanded="false" aria-controls="rateb-pos-modes-menu" aria-label="<?php echo __('pos_more_actions'); ?>">
            <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
        </button>
    </div>
    <span class="visually-hidden" data-pos-toolbar-total aria-live="polite">0.00</span>
</header>
