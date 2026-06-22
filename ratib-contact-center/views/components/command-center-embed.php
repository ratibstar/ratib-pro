<?php
declare(strict_types=1);

/** @var int $tenantId @var string $analyticsApiBase @var string $wsUrl @var bool $canManageTenants */
$tenantId = (int) ($tenantId ?? 0);
$analyticsApiBase = (string) ($analyticsApiBase ?? '');
$wsUrl = (string) ($wsUrl ?? 'polling');
$canManageTenants = !empty($canManageTenants);
$isAr = function_exists('cp_locale') && cp_locale() === 'ar';
?>
<p class="mb-3">
    <a href="<?php echo htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> <?php echo $isAr ? 'مركز الاتصال' : 'Contact Center Hub'; ?>
    </a>
</p>
<div class="rcc-cmd" id="rcc-command-center"
     data-tenant="<?php echo $tenantId; ?>"
     data-api="<?php echo htmlspecialchars($analyticsApiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>"
     data-can-manage-tenants="<?php echo $canManageTenants ? '1' : '0'; ?>"
     data-lang="<?php echo $isAr ? 'ar' : 'en'; ?>">
    <header class="rcc-cmd__header">
        <h2><i class="fas fa-satellite-dish"></i> <?php echo $isAr ? 'مركز القيادة التنفيذي' : 'Executive Command Center'; ?></h2>
        <div id="rcc-cmd-tenant-bar" hidden></div>
        <div class="rcc-cmd__status" id="rcc-cmd-status" aria-live="polite"></div>
    </header>
    <div class="rcc-cmd__grid" id="rcc-cmd-widgets"></div>
    <section class="rcc-cmd__wall" id="rcc-cmd-wall"></section>
</div>
