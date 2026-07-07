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
        <div class="rateb-pos__brand">
            <span class="rateb-pos__brand-mark" aria-hidden="true">R</span>
            <span class="rateb-pos__brand-name">RATEB POS</span>
        </div>
    </div>

    <div class="rateb-pos__header-end">
        <div class="rateb-pos__header-tools" role="group" aria-label="<?php echo __('pos_cashier_tools'); ?>">
            <button type="button" class="rateb-pos__header-tool" data-pos-line-discount-open title="<?php echo __('pos_line_discount'); ?>" aria-label="<?php echo __('pos_line_discount'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/></svg>
            </button>
            <button type="button" class="rateb-pos__header-tool" data-pos-reprint-last title="<?php echo __('pos_reprint_last'); ?>" aria-label="<?php echo __('pos_reprint_last'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            </button>
            <button type="button" class="rateb-pos__header-tool" data-pos-x-report-open title="<?php echo __('pos_x_report'); ?>" aria-label="<?php echo __('pos_x_report'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
            </button>
            <button type="button" class="rateb-pos__header-tool" data-pos-cashier-tools-open title="<?php echo __('pos_cashier_tools'); ?>" aria-label="<?php echo __('pos_cashier_tools'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M12 12v4"/></svg>
            </button>
            <button type="button" class="rateb-pos__header-tool" data-pos-shortcuts-open title="<?php echo __('pos_keyboard_shortcuts'); ?>" aria-label="<?php echo __('pos_keyboard_shortcuts'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M8 14h8"/></svg>
            </button>
        </div>
        <span class="rateb-pos__offline-queue" data-pos-offline-queue hidden title="<?php echo __('pos_offline_queue'); ?>">0</span>
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
        <div class="rateb-pos__theme" role="group" aria-label="<?php echo __('pos_theme_dark'); ?>">
            <button type="button" class="rateb-pos__theme-btn" data-theme-choice="light" aria-pressed="false" title="<?php echo __('pos_theme_light'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            </button>
            <button type="button" class="rateb-pos__theme-btn" data-theme-choice="dark" aria-pressed="true" title="<?php echo __('pos_theme_dark'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </div>
        <span class="rateb-pos__header-user" title="<?php echo __('pos_cashier'); ?>">
            <svg class="rateb-pos__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></span>
        </span>
    </div>
    <span class="visually-hidden" data-pos-toolbar-total aria-live="polite">0.00</span>
</header>
