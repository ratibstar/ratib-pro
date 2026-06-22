<?php
declare(strict_types=1);

/** @var int $tenantId @var string $crmApiBase @var string $wsUrl @var string $route @var bool $canManageTenants */
$tenantId = (int) ($tenantId ?? 0);
$crmApiBase = (string) ($crmApiBase ?? '');
$wsUrl = (string) ($wsUrl ?? 'polling');
$route = (string) ($route ?? 'accounts');
$canManageTenants = !empty($canManageTenants);
$isAr = function_exists('cp_locale') && cp_locale() === 'ar';

$routes = [
    'accounts' => ['icon' => 'fa-building', 'label' => 'Accounts', 'label_ar' => 'الحسابات'],
    'contacts' => ['icon' => 'fa-address-book', 'label' => 'Contacts', 'label_ar' => 'جهات الاتصال'],
    'timeline' => ['icon' => 'fa-timeline', 'label' => 'Timeline', 'label_ar' => 'الجدول الزمني'],
    'tags' => ['icon' => 'fa-tags', 'label' => 'Tags', 'label_ar' => 'الوسوم'],
    'documents' => ['icon' => 'fa-file', 'label' => 'Documents', 'label_ar' => 'المستندات'],
    'sync' => ['icon' => 'fa-sync', 'label' => 'ERP Sync', 'label_ar' => 'مزامنة ERP'],
];
?>
<p class="mb-3">
    <a href="<?php echo htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> <?php echo $isAr ? 'مركز الاتصال' : 'Contact Center Hub'; ?>
    </a>
</p>
<div class="rcc-crm" id="rcc-crm-center"
     data-tenant="<?php echo $tenantId; ?>"
     data-api="<?php echo htmlspecialchars($crmApiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>"
     data-route="<?php echo htmlspecialchars($route, ENT_QUOTES, 'UTF-8'); ?>"
     data-can-manage-tenants="<?php echo $canManageTenants ? '1' : '0'; ?>"
     data-lang="<?php echo $isAr ? 'ar' : 'en'; ?>">
    <aside class="rcc-crm__nav">
        <h2 class="rcc-crm__title"><i class="fas fa-users"></i> <?php echo $isAr ? 'إدارة العملاء' : 'CRM'; ?></h2>
        <div id="rcc-crm-tenant-bar" class="rcc-crm__tenant-bar" hidden></div>
        <nav>
            <?php foreach ($routes as $key => $meta) { ?>
            <a href="<?php echo htmlspecialchars(control_contact_center_crm_page_url($key), ENT_QUOTES, 'UTF-8'); ?>"
               class="rcc-crm__nav-link<?php echo $route === $key ? ' is-active' : ''; ?>">
                <i class="fas <?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                <?php echo htmlspecialchars($isAr ? ($meta['label_ar'] ?? $meta['label']) : $meta['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php } ?>
        </nav>
    </aside>
    <main class="rcc-crm__main">
        <div class="rcc-crm__status" id="rcc-crm-status" aria-live="polite"></div>
        <div class="rcc-crm__panel" id="rcc-crm-panel"></div>
    </main>
</div>
