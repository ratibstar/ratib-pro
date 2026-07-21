<?php
declare(strict_types=1);

/** @var string $activeSection */
$activeSection = (string) ($activeSection ?? 'dashboard');
$navItems = [
    ['key' => 'dashboard', 'label' => 'agent_apps_nav_dashboard', 'icon' => 'fa-gauge-high', 'url' => rateb_url('admin/agent-apps')],
    ['key' => 'requests', 'label' => 'agent_apps_requests', 'icon' => 'fa-users', 'url' => rateb_url('admin/agent-apps/requests')],
    ['key' => 'settings', 'label' => 'agent_apps_settings', 'icon' => 'fa-file-lines', 'url' => rateb_url('admin/agent-apps/settings')],
    ['key' => 'ratings', 'label' => 'agent_apps_ratings', 'icon' => 'fa-star', 'url' => rateb_url('admin/agent-apps/ratings')],
    ['key' => 'complaints', 'label' => 'agent_apps_complaints', 'icon' => 'fa-exclamation-triangle', 'url' => rateb_url('admin/agent-apps/complaints')],
    ['key' => 'notifications', 'label' => 'agent_apps_notifications', 'icon' => 'fa-bell', 'url' => rateb_url('admin/agent-apps/notifications')],
    ['key' => 'payments', 'label' => 'agent_apps_payments', 'icon' => 'fa-credit-card', 'url' => rateb_url('admin/agent-apps/payments')],
    ['key' => 'content', 'label' => 'agent_apps_content', 'icon' => 'fa-align-left', 'url' => rateb_url('admin/agent-apps/content')],
    ['key' => 'offers', 'label' => 'agent_apps_offers', 'icon' => 'fa-image', 'url' => rateb_url('admin/agent-apps/offers')],
    ['key' => 'invoices', 'label' => 'agent_apps_invoices', 'icon' => 'fa-file-invoice', 'url' => rateb_url('admin/agent-apps/invoices')],
];
?>
<aside class="raa-side" aria-label="<?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?>">
    <div class="raa-side__head">
        <i class="fas fa-mobile-screen-button" aria-hidden="true"></i>
        <span><?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?></span>
    </div>
    <nav class="raa-side__nav">
        <?php foreach ($navItems as $item) {
            $on = $activeSection === $item['key'];
            ?>
        <a href="<?php echo Rateb\App\Core\View::escape($item['url']); ?>"
           data-rateb-href="<?php echo Rateb\App\Core\View::escape($item['url']); ?>"
           data-rateb-soft-nav="1"
           class="raa-side__link<?php echo $on ? ' is-active' : ''; ?>">
            <i class="fas <?php echo Rateb\App\Core\View::escape($item['icon']); ?>" aria-hidden="true"></i>
            <span><?php echo Rateb\App\Core\View::escape(__($item['label'])); ?></span>
        </a>
        <?php } ?>
        <a href="<?php echo rateb_url('admin/mobile-apps'); ?>"
           data-rateb-href="<?php echo rateb_url('admin/mobile-apps'); ?>"
           data-rateb-soft-nav="1"
           class="raa-side__link">
            <i class="fas fa-palette" aria-hidden="true"></i>
            <span><?php echo Rateb\App\Core\View::escape(__('mobile_apps_nav')); ?></span>
        </a>
    </nav>
</aside>
