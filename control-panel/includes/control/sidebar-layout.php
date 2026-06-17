<?php
declare(strict_types=1);

/**
 * Sidebar placement for LTR vs RTL shells (nav right in Arabic).
 */
if (!function_exists('control_sidebar_before_main')) {
    function control_sidebar_before_main(): bool
    {
        return !function_exists('cp_is_rtl') || !cp_is_rtl();
    }
}

if (!function_exists('control_render_sidebar')) {
    function control_render_sidebar(): void
    {
        include __DIR__ . '/sidebar.php';
    }
}
