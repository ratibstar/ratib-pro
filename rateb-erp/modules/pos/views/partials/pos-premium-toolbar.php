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

$termLabel = $terminal ? trim((string) ($terminal['code'] ?? '') . ' ' . (string) ($terminal['name'] ?? '')) : '—';
$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
$branchLabel = $branch ? (string) ($branch['name'] ?? '—') : '—';
$warehouseLabel = $warehouse ? (string) ($warehouse['name'] ?? '—') : '—';
$companyLabel = function_exists('rateb_resolve_ops_company_name')
    ? (string) rateb_resolve_ops_company_name()
    : (string) (\Rateb\App\Core\SessionManager::get('rateb_company_name') ?? '—');
$cashierLabel = (string) (\Rateb\App\Core\SessionManager::get('rateb_user_display') ?? \Rateb\App\Core\SessionManager::get('rateb_user_email') ?? '—');
?>
<header class="rateb-pos-premium-header" role="banner">
    <div class="rateb-pos-premium-header__meta" aria-label="<?php echo __('pos_session'); ?>">
        <span class="rateb-pos-meta-chip" title="<?php echo __('company'); ?>">
            <i class="fa-solid fa-building" aria-hidden="true"></i>
            <span class="rateb-pos-meta-chip__label"><?php echo \Rateb\App\Pos\Support\PosView::escape($companyLabel !== '' ? $companyLabel : '—'); ?></span>
        </span>
        <span class="rateb-pos-meta-chip" title="<?php echo __('pos_context_branch'); ?>">
            <i class="fa-solid fa-store" aria-hidden="true"></i>
            <span class="rateb-pos-meta-chip__label"><?php echo \Rateb\App\Pos\Support\PosView::escape($branchLabel); ?></span>
        </span>
        <span class="rateb-pos-meta-chip" title="<?php echo __('pos_context_warehouse'); ?>">
            <i class="fa-solid fa-warehouse" aria-hidden="true"></i>
            <span class="rateb-pos-meta-chip__label"><?php echo \Rateb\App\Pos\Support\PosView::escape($warehouseLabel); ?></span>
        </span>
        <span class="rateb-pos-meta-chip" title="<?php echo __('pos_context_terminal'); ?>">
            <i class="fa-solid fa-cash-register" aria-hidden="true"></i>
            <span class="rateb-pos-meta-chip__label"><?php echo \Rateb\App\Pos\Support\PosView::escape($termLabel); ?></span>
        </span>
        <span class="rateb-pos-meta-chip" title="<?php echo __('pos_context_shift'); ?>">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            <span class="rateb-pos-meta-chip__label"><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?></span>
        </span>
        <span class="rateb-pos-meta-chip" title="<?php echo __('pos_cashier'); ?>">
            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
            <span class="rateb-pos-meta-chip__label"><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></span>
        </span>
    </div>

    <div class="rateb-pos-premium-header__actions">
        <button type="button" class="rateb-pos-header-btn" data-pos-focus-search aria-label="<?php echo __('pos_product_search'); ?>">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </button>
        <button type="button" class="rateb-pos-header-btn" data-pos-focus-barcode aria-label="<?php echo __('pos_barcode_scan'); ?>">
            <i class="fa-solid fa-barcode" aria-hidden="true"></i>
        </button>
        <button type="button" class="rateb-pos-header-btn" data-pos-focus-customer aria-label="<?php echo __('pos_customer'); ?>">
            <i class="fa-solid fa-user" aria-hidden="true"></i>
        </button>
        <span class="rateb-pos-connection" data-pos-connection-status role="status" aria-live="polite">
            <i class="fa-solid fa-wifi" aria-hidden="true"></i>
            <span class="rateb-pos-connection__label"><?php echo __('pos_online'); ?></span>
        </span>
        <button type="button" class="rateb-pos-header-btn" data-pos-notifications aria-label="<?php echo __('pos_notifications'); ?>">
            <i class="fa-regular fa-bell" aria-hidden="true"></i>
        </button>
        <time class="rateb-pos-clock" data-pos-clock datetime="" aria-live="off"></time>
        <div class="rateb-pos-theme-toggle rateb-pos-theme-toggle--compact" role="group" aria-label="<?php echo __('pos_theme_dark'); ?>">
            <button type="button" class="rateb-pos-header-btn" data-theme-choice="dark" aria-pressed="true" title="<?php echo __('pos_theme_dark'); ?>">
                <i class="fa-solid fa-moon" aria-hidden="true"></i>
            </button>
            <button type="button" class="rateb-pos-header-btn" data-theme-choice="light" aria-pressed="false" title="<?php echo __('pos_theme_light'); ?>">
                <i class="fa-solid fa-sun" aria-hidden="true"></i>
            </button>
        </div>
        <div class="btn-group btn-group-sm rateb-pos-lang-toggle" role="group" aria-label="<?php echo __('language'); ?>">
            <a href="<?php echo rateb_url('locale/en'); ?>" class="btn btn-outline-secondary<?php echo $locale === 'en' ? ' active' : ''; ?>" data-locale="en">EN</a>
            <a href="<?php echo rateb_url('locale/ar'); ?>" class="btn btn-outline-secondary<?php echo $locale === 'ar' ? ' active' : ''; ?>" data-locale="ar">ع</a>
        </div>
        <a class="rateb-pos-header-btn" href="<?php echo rateb_app_url('pos/dashboard'); ?>" title="<?php echo __('pos_dashboard'); ?>">
            <i class="fa-solid fa-grip" aria-hidden="true"></i>
        </a>
    </div>

    <div class="rateb-pos-premium-header__total" aria-live="polite">
        <span class="rateb-pos-running-total__label"><?php echo __('pos_total'); ?></span>
        <span class="rateb-pos-running-total__value" data-pos-toolbar-total>0.00</span>
        <span class="rateb-pos-running-total__count" data-pos-toolbar-count>0</span>
    </div>
</header>
