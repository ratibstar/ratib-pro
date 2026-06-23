<?php
declare(strict_types=1);

/** @var int $tenantId @var string $billingApiBase @var string $wsUrl @var string $route */
$tenantId = (int) ($tenantId ?? 0);
$billingApiBase = (string) ($billingApiBase ?? '');
$wsUrl = (string) ($wsUrl ?? 'polling');
$route = (string) ($route ?? 'dashboard');
$isAr = function_exists('cp_locale') && cp_locale() === 'ar';

$routes = [
    'dashboard' => ['icon' => 'fa-chart-pie', 'label' => 'Dashboard', 'label_ar' => 'لوحة الفوترة'],
    'plans' => ['icon' => 'fa-layer-group', 'label' => 'Plans', 'label_ar' => 'الباقات'],
    'subscriptions' => ['icon' => 'fa-repeat', 'label' => 'Subscription', 'label_ar' => 'الاشتراك'],
    'invoices' => ['icon' => 'fa-file-invoice', 'label' => 'Invoices', 'label_ar' => 'الفواتير'],
    'payments' => ['icon' => 'fa-credit-card', 'label' => 'Payments', 'label_ar' => 'المدفوعات'],
    'licenses' => ['icon' => 'fa-key', 'label' => 'Licenses', 'label_ar' => 'التراخيص'],
    'whitelabel' => ['icon' => 'fa-palette', 'label' => 'White Label', 'label_ar' => 'العلامة البيضاء'],
    'reseller' => ['icon' => 'fa-handshake', 'label' => 'Reseller', 'label_ar' => 'الموزعون'],
    'provision' => ['icon' => 'fa-plus-circle', 'label' => 'Provision Tenant', 'label_ar' => 'تجهيز مستأجر'],
];
?>
<p class="mb-3">
    <a href="<?php echo htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> <?php echo $isAr ? 'مركز الاتصال' : 'Contact Center Hub'; ?>
    </a>
</p>
<div class="rcc-billing" id="rcc-billing-center"
     data-tenant="<?php echo $tenantId; ?>"
     data-api="<?php echo htmlspecialchars($billingApiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>"
     data-route="<?php echo htmlspecialchars($route, ENT_QUOTES, 'UTF-8'); ?>"
     data-lang="<?php echo $isAr ? 'ar' : 'en'; ?>">
    <aside class="rcc-billing__nav">
        <h2><i class="fas fa-coins"></i> <?php echo $isAr ? 'فوترة SaaS' : 'SaaS Billing'; ?></h2>
        <nav>
            <?php foreach ($routes as $key => $meta) { ?>
            <a href="<?php echo htmlspecialchars(control_contact_center_billing_page_url($key), ENT_QUOTES, 'UTF-8'); ?>"
               class="rcc-billing__nav-link<?php echo $route === $key ? ' is-active' : ''; ?>">
                <i class="fas <?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                <?php echo htmlspecialchars($isAr ? $meta['label_ar'] : $meta['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php } ?>
        </nav>
    </aside>
    <main class="rcc-billing__main">
        <div id="rcc-billing-status" class="rcc-billing__status" aria-live="polite"></div>
        <div id="rcc-billing-panel" class="rcc-billing__panel"></div>
    </main>
</div>
