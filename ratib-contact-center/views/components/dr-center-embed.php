<?php
declare(strict_types=1);

/** @var int $tenantId @var string $drApiBase @var string $wsUrl @var string $route */
$tenantId = (int) ($tenantId ?? 0);
$drApiBase = (string) ($drApiBase ?? '');
$wsUrl = (string) ($wsUrl ?? 'polling');
$route = (string) ($route ?? 'backups');
$isAr = function_exists('cp_locale') && cp_locale() === 'ar';

$routes = [
    'backups' => ['icon' => 'fa-database', 'label' => 'Backups', 'label_ar' => 'النسخ الاحتياطي'],
    'restore' => ['icon' => 'fa-undo', 'label' => 'Restore', 'label_ar' => 'الاستعادة'],
    'monitors' => ['icon' => 'fa-heartbeat', 'label' => 'Monitoring', 'label_ar' => 'المراقبة'],
    'clusters' => ['icon' => 'fa-server', 'label' => 'PBX HA', 'label_ar' => 'تجمع PBX'],
];
?>
<p class="mb-3">
    <a href="<?php echo htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> <?php echo $isAr ? 'مركز الاتصال' : 'Hub'; ?>
    </a>
</p>
<div class="rcc-dr" id="rcc-dr-center"
     data-tenant="<?php echo $tenantId; ?>"
     data-api="<?php echo htmlspecialchars($drApiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>"
     data-route="<?php echo htmlspecialchars($route, ENT_QUOTES, 'UTF-8'); ?>"
     data-lang="<?php echo $isAr ? 'ar' : 'en'; ?>">
    <aside class="rcc-dr__nav">
        <h2><i class="fas fa-shield-alt"></i> <?php echo $isAr ? 'النسخ والاستعادة' : 'Backup & DR'; ?></h2>
        <nav>
            <?php foreach ($routes as $key => $meta) { ?>
            <a href="<?php echo htmlspecialchars(control_contact_center_backup_page_url($key), ENT_QUOTES, 'UTF-8'); ?>"
               class="rcc-dr__nav-link<?php echo $route === $key ? ' is-active' : ''; ?>">
                <i class="fas <?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                <?php echo htmlspecialchars($isAr ? $meta['label_ar'] : $meta['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php } ?>
        </nav>
    </aside>
    <main class="rcc-dr__main">
        <div id="rcc-dr-status" class="rcc-dr__status"></div>
        <div id="rcc-dr-panel" class="rcc-dr__panel"></div>
    </main>
</div>
