<?php
declare(strict_types=1);

/** @var array<string, mixed> $capabilities */
$nav = is_array($capabilities ?? null) ? ($capabilities['nav'] ?? []) : [];
$navItems = [
    'sales' => ['url' => rateb_app_url('pos/register'), 'label' => __('pos_nav_sales'), 'icon' => 'sale', 'active' => true],
    'customers' => ['url' => '#', 'label' => __('pos_nav_customers'), 'icon' => 'users', 'action' => 'customer'],
    'products' => ['url' => rateb_app_url('inventory'), 'label' => __('pos_nav_products'), 'icon' => 'box'],
    'inventory' => ['url' => '#', 'label' => __('pos_nav_inventory'), 'icon' => 'stock', 'action' => 'stock'],
    'purchases' => ['url' => rateb_app_url('purchase-invoices'), 'label' => __('pos_nav_purchases'), 'icon' => 'cart'],
    'reports' => ['url' => rateb_app_url('pos/reports'), 'label' => __('pos_nav_reports'), 'icon' => 'chart'],
    'settings' => ['url' => rateb_app_url('pos/settings'), 'label' => __('pos_nav_settings'), 'icon' => 'settings'],
];

$iconSvg = static function (string $name): string {
    return match ($name) {
        'users' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'box' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
        'stock' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3h18v4H3zM3 10h18v11H3z"/><path d="M7 14h4"/></svg>',
        'cart' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
        'chart' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 16v-5M12 16V8M17 16v-8"/></svg>',
        'settings' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>',
        default => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h12M6 12h8"/></svg>',
    };
};
?>
<nav class="rateb-pos__nav" aria-label="<?php echo __('pos_nav_section'); ?>" data-pos-register-nav>
    <?php foreach ($navItems as $key => $item): ?>
        <?php if (empty($nav[$key])) { continue; } ?>
        <?php if (($item['action'] ?? '') === 'customer'): ?>
            <button type="button"
                    class="rateb-pos__nav-item"
                    data-pos-nav-customer
                    data-pos-focus-customer>
                <span class="rateb-pos__nav-icon"><?php echo $iconSvg($item['icon']); ?></span>
                <span><?php echo \Rateb\App\Pos\Support\PosView::escape($item['label']); ?></span>
            </button>
        <?php elseif (($item['action'] ?? '') === 'stock'): ?>
            <button type="button"
                    class="rateb-pos__nav-item"
                    data-pos-stock-open
                    <?php echo empty($capabilities['inventoryAdjust'] ?? false) ? 'hidden' : ''; ?>>
                <span class="rateb-pos__nav-icon"><?php echo $iconSvg($item['icon']); ?></span>
                <span><?php echo \Rateb\App\Pos\Support\PosView::escape($item['label']); ?></span>
            </button>
        <?php else: ?>
            <a href="<?php echo \Rateb\App\Pos\Support\PosView::escape($item['url']); ?>"
               class="rateb-pos__nav-item<?php echo !empty($item['active']) ? ' is-active' : ''; ?>"
               <?php echo !empty($item['active']) ? 'aria-current="page"' : ''; ?>>
                <span class="rateb-pos__nav-icon"><?php echo $iconSvg($item['icon']); ?></span>
                <span><?php echo \Rateb\App\Pos\Support\PosView::escape($item['label']); ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
