<?php
declare(strict_types=1);

/**
 * Collapsible sidebar section helpers (paired with system.js initSidebarCollapsibles).
 */
if (!function_exists('control_sidebar_group_open')) {
    function control_sidebar_group_open(string $key, string $label, string $iconClass = 'fa-folder'): void
    {
        $keyEsc = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $iconEsc = htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8');
        echo '<li class="sidebar-collapsible" data-sidebar-group="' . $keyEsc . '">';
        echo '<button type="button" class="sidebar-item sidebar-item-toggle" data-sidebar-toggle="' . $keyEsc . '" aria-expanded="false">';
        echo '<i class="fas fa-chevron-down sidebar-toggle-icon" aria-hidden="true"></i>';
        if ($iconEsc !== '') {
            echo '<i class="fas ' . $iconEsc . '"></i>';
        }
        echo '<span>' . $labelEsc . '</span>';
        echo '</button>';
        echo '<ul class="sidebar-submenu" data-sidebar-panel="' . $keyEsc . '" hidden>';
    }
}

if (!function_exists('control_sidebar_group_close')) {
    function control_sidebar_group_close(): void
    {
        echo '</ul></li>';
    }
}
