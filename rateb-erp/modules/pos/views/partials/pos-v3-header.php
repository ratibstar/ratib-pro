<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var string $locale */
$context = $context ?? [];
$locale = $locale ?? rateb_locale();
$terminal = $context['terminal'] ?? null;
$shift = $context['shift'] ?? null;
$branch = $context['branch'] ?? null;

$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
$branchLabel = $branch ? (string) ($branch['name'] ?? '—') : '—';
$termLabel = $terminal ? trim((string) ($terminal['code'] ?? '') . ' · ' . (string) ($terminal['name'] ?? '')) : '—';
$cashierLabel = (string) (\Rateb\App\Core\SessionManager::get('rateb_user_display') ?? \Rateb\App\Core\SessionManager::get('rateb_user_email') ?? '—');
?>
<header class="rateb-pos-v3__bar" role="banner">
    <div class="rateb-pos-v3__bar-start">
        <span class="rateb-pos-v3__meta" title="<?php echo __('pos_cashier'); ?>">
            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></span>
        </span>
        <span class="rateb-pos-v3__meta" title="<?php echo __('pos_context_shift'); ?>">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?></span>
        </span>
        <span class="rateb-pos-v3__meta" title="<?php echo __('pos_context_branch'); ?>">
            <i class="fa-solid fa-store" aria-hidden="true"></i>
            <span><?php echo \Rateb\App\Pos\Support\PosView::escape($branchLabel); ?></span>
        </span>
    </div>

    <button type="button" class="rateb-pos-v3__customer" data-pos-focus-customer aria-label="<?php echo __('pos_customer'); ?>">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <span data-pos-toolbar-customer><?php echo __('pos_walk_in_customer'); ?></span>
    </button>

    <div class="rateb-pos-v3__bar-end">
        <time class="rateb-pos-v3__clock" data-pos-clock datetime="" aria-live="off"></time>
        <span class="rateb-pos-v3__connection" data-pos-connection-status role="status" aria-live="polite">
            <i class="fa-solid fa-wifi" aria-hidden="true"></i>
            <span class="rateb-pos-connection__label"><?php echo __('pos_online'); ?></span>
        </span>
        <div class="rateb-pos-v3__running-total" aria-live="polite">
            <span class="rateb-pos-v3__running-total-label"><?php echo __('pos_total'); ?></span>
            <span class="rateb-pos-v3__running-total-value" data-pos-toolbar-total>0.00</span>
        </div>
        <button type="button" class="rateb-pos-v3__icon-btn" data-pos-settings-toggle aria-expanded="false" aria-controls="rateb-pos-v3-settings" aria-label="<?php echo __('pos_settings'); ?>">
            <i class="fa-solid fa-gear" aria-hidden="true"></i>
        </button>
    </div>

    <div class="rateb-pos-v3__settings" id="rateb-pos-v3-settings" data-pos-settings hidden>
        <span class="rateb-pos-v3__settings-label"><?php echo __('pos_context_terminal'); ?></span>
        <span class="rateb-pos-v3__settings-value"><?php echo \Rateb\App\Pos\Support\PosView::escape($termLabel); ?></span>
        <div class="rateb-pos-v3__settings-lang" role="group" aria-label="<?php echo __('language'); ?>">
            <a href="<?php echo rateb_url('locale/en'); ?>" class="rateb-pos-v3__settings-chip<?php echo $locale === 'en' ? ' is-active' : ''; ?>" data-locale="en">EN</a>
            <a href="<?php echo rateb_url('locale/ar'); ?>" class="rateb-pos-v3__settings-chip<?php echo $locale === 'ar' ? ' is-active' : ''; ?>" data-locale="ar">ع</a>
        </div>
        <div class="rateb-pos-v3__settings-theme" role="group" aria-label="<?php echo __('pos_theme_dark'); ?>">
            <button type="button" class="rateb-pos-v3__settings-chip" data-theme-choice="dark" aria-pressed="true"><?php echo __('pos_theme_dark'); ?></button>
            <button type="button" class="rateb-pos-v3__settings-chip" data-theme-choice="light" aria-pressed="false"><?php echo __('pos_theme_light'); ?></button>
        </div>
        <a class="rateb-pos-v3__settings-link" href="<?php echo rateb_app_url('pos/dashboard'); ?>">
            <i class="fa-solid fa-grip" aria-hidden="true"></i> <?php echo __('pos_dashboard'); ?>
        </a>
        <button type="button" class="rateb-pos-v3__settings-link" data-pos-focus-search>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> <?php echo __('pos_product_search'); ?>
        </button>
        <button type="button" class="rateb-pos-v3__settings-link" data-pos-notifications>
            <i class="fa-regular fa-bell" aria-hidden="true"></i> <?php echo __('pos_notifications'); ?>
        </button>
    </div>
</header>
