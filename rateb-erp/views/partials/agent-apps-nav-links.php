<?php
declare(strict_types=1);

/** Agent App Management links (shared by the oversight subgroup and the standalone section). */
return [
    ['admin/agent-apps', 'agent_apps_nav_dashboard', 'fa-gauge-high', 'mobile_apps.view'],
    ['admin/agent-apps/requests', 'agent_apps_requests', 'fa-briefcase', 'mobile_apps.view'],
    ['admin/agent-apps/complaints', 'agent_apps_complaints', 'fa-exclamation-triangle', 'mobile_apps.view'],
    ['admin/agent-apps/content', 'agent_apps_content', 'fa-file-lines', 'mobile_apps.view'],
    ['admin/agent-apps/offers', 'agent_apps_offers', 'fa-image', 'mobile_apps.view'],
    ['admin/agent-apps/ratings', 'agent_apps_ratings', 'fa-star', 'mobile_apps.view'],
    ['admin/agent-apps/notifications', 'agent_apps_notifications', 'fa-bell', 'mobile_apps.view'],
    ['admin/agent-apps/payments', 'agent_apps_payments', 'fa-credit-card', 'mobile_apps.view'],
    ['admin/agent-apps/settings', 'agent_apps_settings', 'fa-sliders', 'mobile_apps.view'],
    ['admin/agent-apps/invoices', 'agent_apps_invoices', 'fa-file-invoice', 'mobile_apps.view'],
];
