<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var string $locale */
$context = $context ?? [];
$locale = $locale ?? rateb_locale();
$terminal = $context['terminal'] ?? null;
$shift = $context['shift'] ?? null;
$branch = $context['branch'] ?? null;
$warehouse = $context['warehouse'] ?? null;

$termLabel = $terminal ? trim((string) ($terminal['code'] ?? '') . ' · ' . (string) ($terminal['name'] ?? '')) : '—';
$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
$branchLabel = $branch ? (string) ($branch['name'] ?? '—') : '—';
$warehouseLabel = $warehouse ? (string) ($warehouse['name'] ?? '—') : '—';
$cashierLabel = (string) (\Rateb\App\Core\SessionManager::get('rateb_user_display') ?? \Rateb\App\Core\SessionManager::get('rateb_user_email') ?? '—');
?>
<header class="rateb-pos-topbar" role="banner">
    <div class="rateb-pos-topbar__start">
        <div class="rateb-pos-topbar__cashier" title="<?php echo __('pos_cashier'); ?>">
            <span class="rateb-pos-topbar__avatar" aria-hidden="true">
                <i class="fa-solid fa-user-tie"></i>
            </span>
            <span class="rateb-pos-topbar__cashier-name"><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></span>
        </div>
        <span class="rateb-pos-topbar__pill" title="<?php echo __('pos_context_shift'); ?>">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?></span>
        </span>
        <span class="rateb-pos-topbar__pill" title="<?php echo __('pos_context_terminal'); ?>">
            <i class="fa-solid fa-cash-register" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($termLabel); ?></span>
        </span>
    </div>

    <button type="button" class="rateb-pos-topbar__customer" data-pos-focus-customer aria-label="<?php echo __('pos_customer'); ?>">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <span data-pos-toolbar-customer><?php echo __('pos_walk_in_customer'); ?></span>
    </button>

    <div class="rateb-pos-topbar__end">
        <time class="rateb-pos-topbar__clock" data-pos-clock datetime="" aria-live="off"></time>
        <button type="button" class="rateb-pos-topbar__icon" data-pos-focus-search aria-label="<?php echo __('pos_product_search'); ?>">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </button>
        <button type="button" class="rateb-pos-topbar__icon" data-pos-notifications aria-label="<?php echo __('pos_notifications'); ?>">
            <i class="fa-regular fa-bell" aria-hidden="true"></i>
        </button>
        <button type="button" class="rateb-pos-topbar__icon" data-pos-overflow-toggle aria-expanded="false" aria-controls="rateb-pos-overflow-menu" aria-label="<?php echo __('pos_settings'); ?>">
            <i class="fa-solid fa-gear" aria-hidden="true"></i>
        </button>
    </div>

    <div class="rateb-pos-overflow" id="rateb-pos-overflow-menu" data-pos-overflow hidden>
        <div class="rateb-pos-overflow__section">
            <p class="rateb-pos-overflow__title"><?php echo __('pos_session'); ?></p>
            <p class="rateb-pos-overflow__row"><i class="fa-solid fa-store"></i> <?php echo \Rateb\App\Pos\Support\PosView::escape($branchLabel); ?></p>
            <p class="rateb-pos-overflow__row"><i class="fa-solid fa-warehouse"></i> <?php echo \Rateb\App\Pos\Support\PosView::escape($warehouseLabel); ?></p>
            <span class="rateb-pos-connection rateb-pos-connection--compact" data-pos-connection-status role="status" aria-live="polite">
                <i class="fa-solid fa-wifi" aria-hidden="true"></i>
                <span class="rateb-pos-connection__label"><?php echo __('pos_online'); ?></span>
            </span>
        </div>
        <div class="rateb-pos-overflow__actions">
            <button type="button" class="rateb-pos-overflow__btn" data-pos-focus-barcode>
                <i class="fa-solid fa-barcode" aria-hidden="true"></i> <?php echo __('pos_barcode_scan'); ?>
            </button>
            <div class="rateb-pos-theme-toggle rateb-pos-theme-toggle--overflow" role="group" aria-label="<?php echo __('pos_theme_dark'); ?>">
                <button type="button" class="rateb-pos-overflow__btn" data-theme-choice="dark" aria-pressed="true">
                    <i class="fa-solid fa-moon" aria-hidden="true"></i> <?php echo __('pos_theme_dark'); ?>
                </button>
                <button type="button" class="rateb-pos-overflow__btn" data-theme-choice="light" aria-pressed="false">
                    <i class="fa-solid fa-sun" aria-hidden="true"></i> <?php echo __('pos_theme_light'); ?>
                </button>
            </div>
            <div class="rateb-pos-lang-toggle rateb-pos-lang-toggle--overflow" role="group" aria-label="<?php echo __('language'); ?>">
                <a href="<?php echo rateb_url('locale/en'); ?>" class="rateb-pos-overflow__btn<?php echo $locale === 'en' ? ' is-active' : ''; ?>" data-locale="en">English</a>
                <a href="<?php echo rateb_url('locale/ar'); ?>" class="rateb-pos-overflow__btn<?php echo $locale === 'ar' ? ' is-active' : ''; ?>" data-locale="ar">عربي</a>
            </div>
            <a class="rateb-pos-overflow__btn" href="<?php echo rateb_app_url('pos/dashboard'); ?>">
                <i class="fa-solid fa-grip" aria-hidden="true"></i> <?php echo __('pos_dashboard'); ?>
            </a>
        </div>
    </div>
</header>
