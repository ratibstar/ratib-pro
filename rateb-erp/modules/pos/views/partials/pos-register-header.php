<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
$shift = $context['shift'] ?? null;
$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
$cashierLabel = (string) (\Rateb\App\Core\SessionManager::get('rateb_user_display') ?? \Rateb\App\Core\SessionManager::get('rateb_user_email') ?? '—');
$locale = rateb_locale();
?>
<header class="rateb-pos__header" role="banner">
    <div class="rateb-pos__header-start">
        <button type="button" class="rateb-pos__header-menu" data-pos-modes-toggle aria-expanded="false" aria-controls="rateb-pos-modes-menu" aria-label="<?php echo __('pos_more_actions'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="rateb-pos__brand">
            <span class="rateb-pos__brand-mark" aria-hidden="true">R</span>
            <span class="rateb-pos__brand-name">RATEB POS</span>
        </div>
    </div>

    <div class="rateb-pos__header-end">
        <span class="rateb-pos__header-chip" title="<?php echo __('pos_context_shift'); ?>">
            <svg class="rateb-pos__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?></span>
        </span>
        <time class="rateb-pos__clock" data-pos-clock datetime="" aria-live="off"></time>
        <span class="rateb-pos__connection" data-pos-connection-status role="status" aria-live="polite">
            <svg class="rateb-pos__icon rateb-pos__connection-dot" width="8" height="8" viewBox="0 0 8 8" fill="currentColor" aria-hidden="true"><circle cx="4" cy="4" r="4"/></svg>
            <span class="rateb-pos-connection__label"><?php echo __('pos_online'); ?></span>
        </span>
        <div class="rateb-pos__lang" role="group" aria-label="<?php echo __('language'); ?>">
            <a href="<?php echo rateb_url('locale/en'); ?>" class="rateb-pos__lang-btn<?php echo $locale === 'en' ? ' is-active' : ''; ?>" data-locale="en" lang="en">EN</a>
            <a href="<?php echo rateb_url('locale/ar'); ?>" class="rateb-pos__lang-btn<?php echo $locale === 'ar' ? ' is-active' : ''; ?>" data-locale="ar" lang="ar">ع</a>
        </div>
        <span class="rateb-pos__header-user" title="<?php echo __('pos_cashier'); ?>">
            <svg class="rateb-pos__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></span>
        </span>
    </div>
    <span class="visually-hidden" data-pos-toolbar-total aria-live="polite">0.00</span>
</header>
