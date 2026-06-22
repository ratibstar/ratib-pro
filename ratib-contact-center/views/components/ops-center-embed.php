<?php
declare(strict_types=1);

/**
 * Production Operations Center embed — Phase 8.
 *
 * @var int $tenantId
 * @var string $opsApiBase
 * @var string $wsUrl
 * @var string $route
 * @var bool $canManageTenants
 */
$tenantId = (int) ($tenantId ?? 0);
$opsApiBase = (string) ($opsApiBase ?? '');
$wsUrl = (string) ($wsUrl ?? 'polling');
$route = (string) ($route ?? 'health');
$canManageTenants = !empty($canManageTenants);

$opsRoutes = [
    'health' => ['icon' => 'fa-heart-pulse', 'label' => 'Health Center'],
    'pbx' => ['icon' => 'fa-server', 'label' => 'PBX Wizard'],
    'sip' => ['icon' => 'fa-phone', 'label' => 'SIP Extensions'],
    'queues' => ['icon' => 'fa-list-ol', 'label' => 'Queues'],
    'ivr' => ['icon' => 'fa-diagram-project', 'label' => 'IVR Builder'],
    'agents' => ['icon' => 'fa-user-headset', 'label' => 'Agent Provisioning'],
    'webrtc' => ['icon' => 'fa-wifi', 'label' => 'WebRTC Diagnostics'],
    'ami' => ['icon' => 'fa-plug', 'label' => 'AMI Diagnostics'],
    'hub' => ['icon' => 'fa-broadcast-tower', 'label' => 'Realtime Hub'],
    'golive' => ['icon' => 'fa-clipboard-check', 'label' => 'Go-Live Checklist'],
];
?>
<p class="mb-3">
    <a href="<?php echo htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Contact Center Hub
    </a>
</p>

<div class="rcc-ops" id="rcc-ops-center"
     data-tenant="<?php echo $tenantId; ?>"
     data-api="<?php echo htmlspecialchars($opsApiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>"
     data-route="<?php echo htmlspecialchars($route, ENT_QUOTES, 'UTF-8'); ?>"
     data-can-manage-tenants="<?php echo $canManageTenants ? '1' : '0'; ?>">
    <aside class="rcc-ops__nav">
        <h2 class="rcc-ops__title"><i class="fas fa-screwdriver-wrench"></i> Operations</h2>
        <div id="rcc-ops-tenant-bar" class="rcc-ops__tenant-bar" hidden></div>
        <nav>
            <?php foreach ($opsRoutes as $key => $meta) { ?>
            <a href="<?php echo htmlspecialchars(control_contact_center_ops_page_url($key), ENT_QUOTES, 'UTF-8'); ?>"
               class="rcc-ops__nav-link<?php echo $route === $key ? ' is-active' : ''; ?>"
               data-ops-route="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas <?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php } ?>
        </nav>
    </aside>
    <main class="rcc-ops__main">
        <div class="rcc-ops__status" id="rcc-ops-status" aria-live="polite"></div>
        <div class="rcc-ops__panel" id="rcc-ops-panel"></div>
    </main>
</div>
