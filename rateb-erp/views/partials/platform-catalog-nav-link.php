<?php
declare(strict_types=1);

if (!function_exists('rateb_platform_catalog_nav_enabled') || !rateb_platform_catalog_nav_enabled()) {
    return;
}
if (function_exists('rateb_company_platform_feature_enabled')
    && !rateb_company_platform_feature_enabled('platform_product_catalog')) {
    return;
}
if (function_exists('rateb_can') && !rateb_is_super_admin() && !rateb_can('platform_catalog.manage')) {
    return;
}
$entryUrl = function_exists('rateb_platform_catalog_entry_url')
    ? rateb_platform_catalog_entry_url()
    : rateb_platform_catalog_admin_url();
$entryUrl = htmlspecialchars($entryUrl, ENT_QUOTES, 'UTF-8');
?>
<a href="<?php echo $entryUrl; ?>"
   class="rateb-nav-link"
   data-rateb-full-nav="1"
   data-rateb-href="<?php echo $entryUrl; ?>"
   onclick="event.preventDefault();event.stopPropagation();try{event.stopImmediatePropagation();}catch(e){}window.location.assign(this.getAttribute('href'));return false;">
    <i class="fas fa-boxes-stacked"></i><span><?php echo __('platform_catalog_admin'); ?></span>
</a>
