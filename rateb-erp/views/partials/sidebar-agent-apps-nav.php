<?php
declare(strict_types=1);

/**
 * Agent App Management group — place immediately before Access Control.
 * Expects $adminSection from sidebar-nav.php.
 */
if (!isset($adminSection) || !is_callable($adminSection)) {
    return;
}
if (!rateb_nav_can('mobile_apps.view')) {
    return;
}
// Platform super-admin gets the whole group under Oversight → Mobile Apps instead.
if (rateb_is_super_admin() && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
    return;
}
$agentAppsLinks = require RATEB_ROOT . '/views/partials/agent-apps-nav-links.php';
$agentAppsLinks[] = ['admin/mobile-apps', 'mobile_apps_nav', 'fa-mobile-alt', 'mobile_apps.view'];
$adminSection(__('agent_apps_section'), $agentAppsLinks, 'fa-mobile-screen-button');
