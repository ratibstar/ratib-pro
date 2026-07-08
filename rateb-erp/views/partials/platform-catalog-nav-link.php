<?php
declare(strict_types=1);

if (!function_exists('rateb_platform_catalog_nav_enabled') || !rateb_platform_catalog_nav_enabled()) {
    return;
}
?>
<a href="<?php echo htmlspecialchars(rateb_platform_catalog_admin_url(), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav-link">
    <i class="fas fa-boxes-stacked"></i><span><?php echo __('platform_catalog_admin'); ?></span>
</a>
