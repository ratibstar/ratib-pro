<?php
declare(strict_types=1);

/** @var int $tenantId @var string $marketplaceApiBase */
$tenantId = (int) ($tenantId ?? 0);
$marketplaceApiBase = (string) ($marketplaceApiBase ?? '');
$isAr = function_exists('cp_locale') && cp_locale() === 'ar';
?>
<p class="mb-3">
    <a href="<?php echo htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> <?php echo $isAr ? 'مركز الاتصال' : 'Hub'; ?>
    </a>
</p>
<div class="rcc-marketplace" id="rcc-marketplace-center"
     data-tenant="<?php echo $tenantId; ?>"
     data-api="<?php echo htmlspecialchars($marketplaceApiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-lang="<?php echo $isAr ? 'ar' : 'en'; ?>">
    <h2><i class="fas fa-store"></i> <?php echo $isAr ? 'سوق الإضافات' : 'Marketplace'; ?></h2>
    <div id="rcc-marketplace-status" class="rcc-marketplace__status"></div>
    <div id="rcc-marketplace-catalog" class="rcc-marketplace__grid"></div>
    <h3><?php echo $isAr ? 'إضافاتك النشطة' : 'Your add-ons'; ?></h3>
    <div id="rcc-marketplace-active"></div>
</div>
