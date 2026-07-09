<?php
declare(strict_types=1);

/** @var array<string, mixed>|null $registerConfig */
$capabilities = is_array($registerConfig['capabilities'] ?? null) ? $registerConfig['capabilities'] : [];
$nav = is_array($capabilities['nav'] ?? null) ? $capabilities['nav'] : [];

$items = [
    ['key' => 'register', 'url' => rateb_app_url('pos/register'), 'label' => __('pos_nav_sales'), 'icon' => 'sales', 'active' => true],
    ['key' => 'customers', 'url' => rateb_app_url('pos/register'), 'label' => __('pos_nav_customers'), 'icon' => 'customers', 'action' => 'customer'],
    ['key' => 'products', 'url' => rateb_app_url('pos/register'), 'label' => __('pos_nav_products'), 'icon' => 'products', 'action' => 'catalog'],
    ['key' => 'inventory', 'url' => rateb_app_url('pos/register'), 'label' => __('pos_nav_inventory'), 'icon' => 'inventory', 'action' => 'stock'],
    ['key' => 'purchases', 'url' => rateb_app_url('procurement/purchase-orders'), 'label' => __('pos_nav_purchases'), 'icon' => 'purchases'],
    ['key' => 'reports', 'url' => rateb_app_url('pos/reports'), 'label' => __('pos_reports'), 'icon' => 'reports'],
    ['key' => 'settings', 'url' => rateb_app_url('pos/settings'), 'label' => __('pos_settings'), 'icon' => 'settings'],
];

$icons = [
    'sales' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>',
    'customers' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'products' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
    'inventory' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
    'purchases' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
    'reports' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>',
    'settings' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>',
];
?>
<nav class="rateb-pos__nav" aria-label="<?php echo __('pos_nav_section'); ?>" data-pos-register-nav>
<?php foreach ($items as $item):
    $key = (string) $item['key'];
    $allowed = $nav === [] || !empty($nav[$key]);
    if (!$allowed) {
        continue;
    }
    $active = !empty($item['active']);
    $action = (string) ($item['action'] ?? '');
    $isButton = $action !== '';
?>
<?php if ($isButton): ?>
    <button type="button"
            class="rateb-pos__nav-item<?php echo $active ? ' is-active' : ''; ?>"
            data-pos-nav-action="<?php echo \Rateb\App\Pos\Support\PosView::escape($action); ?>"
            <?php echo $active ? 'aria-current="page"' : ''; ?>>
        <span class="rateb-pos__nav-icon" aria-hidden="true"><?php echo $icons[$item['icon']] ?? ''; ?></span>
        <span><?php echo \Rateb\App\Pos\Support\PosView::escape($item['label']); ?></span>
    </button>
<?php else: ?>
    <a href="<?php echo \Rateb\App\Pos\Support\PosView::escape($item['url']); ?>"
       class="rateb-pos__nav-item<?php echo $active ? ' is-active' : ''; ?>"
       <?php echo $active ? 'aria-current="page"' : ''; ?>>
        <span class="rateb-pos__nav-icon" aria-hidden="true"><?php echo $icons[$item['icon']] ?? ''; ?></span>
        <span><?php echo \Rateb\App\Pos\Support\PosView::escape($item['label']); ?></span>
    </a>
<?php endif; ?>
<?php endforeach; ?>
</nav>
