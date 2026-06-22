<?php
declare(strict_types=1);

/**
 * Supervisor & Workforce Management embed — Phase 9.
 *
 * @var int $tenantId
 * @var string $supervisorApiBase
 * @var string $wsUrl
 * @var string $route
 * @var bool $canManageTenants
 */
$tenantId = (int) ($tenantId ?? 0);
$supervisorApiBase = (string) ($supervisorApiBase ?? '');
$wsUrl = (string) ($wsUrl ?? 'polling');
$route = (string) ($route ?? 'dashboard');
$canManageTenants = !empty($canManageTenants);

$routes = [
    'dashboard' => ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'label_ar' => 'لوحة التحكم'],
    'wallboard' => ['icon' => 'fa-tv', 'label' => 'Live Wallboard', 'label_ar' => 'لوحة حية'],
    'queues' => ['icon' => 'fa-list-ol', 'label' => 'Queue Monitor', 'label_ar' => 'مراقبة الطوابير'],
    'agents' => ['icon' => 'fa-user-headset', 'label' => 'Agent Monitor', 'label_ar' => 'مراقبة الوكلاء'],
    'sla' => ['icon' => 'fa-clock', 'label' => 'SLA Dashboard', 'label_ar' => 'اتفاقية الخدمة'],
    'wfm' => ['icon' => 'fa-people-group', 'label' => 'Workforce', 'label_ar' => 'إدارة القوى'],
    'shifts' => ['icon' => 'fa-calendar-days', 'label' => 'Shift Planner', 'label_ar' => 'جدول الورديات'],
    'attendance' => ['icon' => 'fa-clipboard-user', 'label' => 'Attendance', 'label_ar' => 'الحضور'],
    'breaks' => ['icon' => 'fa-mug-hot', 'label' => 'Breaks', 'label_ar' => 'الاستراحات'],
    'occupancy' => ['icon' => 'fa-chart-pie', 'label' => 'Occupancy', 'label_ar' => 'الإشغال'],
    'adherence' => ['icon' => 'fa-check-double', 'label' => 'Adherence', 'label_ar' => 'الالتزام'],
    'alerts' => ['icon' => 'fa-bell', 'label' => 'Alerts', 'label_ar' => 'التنبيهات'],
    'reports' => ['icon' => 'fa-file-csv', 'label' => 'Reports', 'label_ar' => 'التقارير'],
];
$isAr = function_exists('cp_locale') && cp_locale() === 'ar';
?>
<p class="mb-3">
    <a href="<?php echo htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> <?php echo $isAr ? 'مركز الاتصال' : 'Contact Center Hub'; ?>
    </a>
</p>

<div class="rcc-sup" id="rcc-supervisor-center"
     data-tenant="<?php echo $tenantId; ?>"
     data-api="<?php echo htmlspecialchars($supervisorApiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>"
     data-route="<?php echo htmlspecialchars($route, ENT_QUOTES, 'UTF-8'); ?>"
     data-can-manage-tenants="<?php echo $canManageTenants ? '1' : '0'; ?>"
     data-lang="<?php echo $isAr ? 'ar' : 'en'; ?>">
    <aside class="rcc-sup__nav">
        <h2 class="rcc-sup__title"><i class="fas fa-chart-line"></i> <?php echo $isAr ? 'لوحة المشرف' : 'Supervisor'; ?></h2>
        <div id="rcc-sup-tenant-bar" class="rcc-sup__tenant-bar" hidden></div>
        <nav>
            <?php foreach ($routes as $key => $meta) { ?>
            <a href="<?php echo htmlspecialchars(control_contact_center_supervisor_page_url($key), ENT_QUOTES, 'UTF-8'); ?>"
               class="rcc-sup__nav-link<?php echo $route === $key ? ' is-active' : ''; ?>"
               data-sup-route="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas <?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                <?php echo htmlspecialchars($isAr ? ($meta['label_ar'] ?? $meta['label']) : $meta['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php } ?>
        </nav>
    </aside>
    <main class="rcc-sup__main">
        <div class="rcc-sup__status" id="rcc-sup-status" aria-live="polite"></div>
        <div class="rcc-sup__panel" id="rcc-sup-panel"></div>
    </main>
</div>
