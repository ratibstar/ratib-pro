<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var string $locale */
$context = $context ?? [];
$locale = $locale ?? rateb_locale();
$terminal = $context['terminal'] ?? null;
$shift = $context['shift'] ?? null;
$branch = $context['branch'] ?? null;

$termLabel = $terminal ? trim((string) ($terminal['code'] ?? '') . ' · ' . (string) ($terminal['name'] ?? '')) : '—';
$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
$branchLabel = $branch ? (string) ($branch['name'] ?? '—') : '—';
$cashierLabel = (string) (\Rateb\App\Core\SessionManager::get('rateb_user_display') ?? \Rateb\App\Core\SessionManager::get('rateb_user_email') ?? '—');
?>
<header class="rateb-pos-v2__header" role="banner">
    <div class="rateb-pos-v2__header-group rateb-pos-v2__header-group--session">
        <span class="rateb-pos-v2__chip" title="<?php echo __('pos_cashier'); ?>">
            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></span>
        </span>
        <span class="rateb-pos-v2__chip" title="<?php echo __('pos_context_shift'); ?>">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?></span>
        </span>
        <span class="rateb-pos-v2__chip" title="<?php echo __('pos_context_branch'); ?>">
            <i class="fa-solid fa-store" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($branchLabel); ?></span>
        </span>
        <span class="rateb-pos-v2__chip" title="<?php echo __('pos_context_terminal'); ?>">
            <i class="fa-solid fa-cash-register" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($termLabel); ?></span>
        </span>
    </div>

    <button type="button" class="rateb-pos-v2__customer" data-pos-focus-customer aria-label="<?php echo __('pos_customer'); ?>">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <span data-pos-toolbar-customer><?php echo __('pos_walk_in_customer'); ?></span>
    </button>

    <div class="rateb-pos-v2__header-group rateb-pos-v2__header-group--tools">
        <time class="rateb-pos-v2__clock" data-pos-clock datetime="" aria-live="off"></time>
        <span class="rateb-pos-v2__connection" data-pos-connection-status role="status" aria-live="polite">
            <i class="fa-solid fa-wifi" aria-hidden="true"></i>
            <span class="rateb-pos-connection__label"><?php echo __('pos_online'); ?></span>
        </span>
        <div class="rateb-pos-v2__lang" role="group" aria-label="<?php echo __('language'); ?>">
            <a href="<?php echo rateb_url('locale/en'); ?>" class="rateb-pos-v2__lang-btn<?php echo $locale === 'en' ? ' is-active' : ''; ?>" data-locale="en">EN</a>
            <a href="<?php echo rateb_url('locale/ar'); ?>" class="rateb-pos-v2__lang-btn<?php echo $locale === 'ar' ? ' is-active' : ''; ?>" data-locale="ar">ع</a>
        </div>
        <div class="rateb-pos-v2__theme" role="group" aria-label="<?php echo __('pos_theme_dark'); ?>">
            <button type="button" class="rateb-pos-v2__icon-btn" data-theme-choice="dark" aria-pressed="true" title="<?php echo __('pos_theme_dark'); ?>">
                <i class="fa-solid fa-moon" aria-hidden="true"></i>
            </button>
            <button type="button" class="rateb-pos-v2__icon-btn" data-theme-choice="light" aria-pressed="false" title="<?php echo __('pos_theme_light'); ?>">
                <i class="fa-solid fa-sun" aria-hidden="true"></i>
            </button>
        </div>
        <div class="rateb-pos-v2__running-total" aria-live="polite">
            <span class="rateb-pos-v2__running-total-label"><?php echo __('pos_total'); ?></span>
            <span class="rateb-pos-v2__running-total-value" data-pos-toolbar-total>0.00</span>
        </div>
        <button type="button" class="rateb-pos-v2__icon-btn" data-pos-settings-toggle aria-expanded="false" aria-controls="rateb-pos-v2-settings" aria-label="<?php echo __('pos_settings'); ?>">
            <i class="fa-solid fa-gear" aria-hidden="true"></i>
        </button>
    </div>

    <div class="rateb-pos-v2__settings" id="rateb-pos-v2-settings" data-pos-settings hidden>
        <a class="rateb-pos-v2__settings-link" href="<?php echo rateb_app_url('pos/dashboard'); ?>">
            <i class="fa-solid fa-grip" aria-hidden="true"></i> <?php echo __('pos_dashboard'); ?>
        </a>
        <button type="button" class="rateb-pos-v2__settings-link" data-pos-focus-search>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> <?php echo __('pos_product_search'); ?>
        </button>
        <button type="button" class="rateb-pos-v2__settings-link" data-pos-notifications>
            <i class="fa-regular fa-bell" aria-hidden="true"></i> <?php echo __('pos_notifications'); ?>
        </button>
    </div>
</header>
