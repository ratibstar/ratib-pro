<?php
declare(strict_types=1);

if (!function_exists('rateb_platform_catalog_nav_enabled') || !rateb_platform_catalog_nav_enabled()) {
    return;
}
$entryUrl = function_exists('rateb_platform_catalog_entry_url')
    ? rateb_platform_catalog_entry_url()
    : rateb_platform_catalog_admin_url();
$entryUrl = htmlspecialchars($entryUrl, ENT_QUOTES, 'UTF-8');
?>
<a href="<?php echo $entryUrl; ?>" class="rateb-nav-link" target="_blank" rel="noopener noreferrer">
    <i class="fas fa-boxes-stacked"></i><span><?php echo __('platform_catalog_admin'); ?></span>
</a>
