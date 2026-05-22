<?php
/**
 * Enterprise terminology map — public and in-app user-facing labels (RATEB).
 */
declare(strict_types=1);

if (!function_exists('ratib_enterprise_term')) {
    function ratib_enterprise_term(string $legacy): string
    {
        static $map = [
            'Dashboard' => 'Dashboard',
            'dashboard' => 'dashboard',
            'Reports' => 'Reports',
            'reports' => 'reports',
            'Notifications' => 'Notifications',
            'notifications' => 'notifications',
            'Settings' => 'System Settings',
            'settings' => 'system settings',
            'Admin Panel' => 'Control Panel Settings',
            'admin panel' => 'control panel settings',
            'GPS Tracking' => 'GPS Tracking',
            'GPS tracking' => 'gps tracking',
            'Tracking' => 'Tracking',
            'Worker Files' => 'Worker Files',
            'Map' => 'Tracking Map',
            'Agency Portal' => 'Agency Portal',
            'CRM' => 'CRM',
        ];

        return $map[$legacy] ?? $legacy;
    }
}

if (!function_exists('ratib_enterprise_brand_meta_title')) {
    function ratib_enterprise_brand_meta_title(): string
    {
        return function_exists('ratib_brand_full_title')
            ? ratib_brand_full_title()
            : 'RATEB — Recruitment Automation & Telemetry Enterprise Base';
    }
}

if (!function_exists('ratib_enterprise_brand_meta_description')) {
    function ratib_enterprise_brand_meta_description(): string
    {
        return function_exists('ratib_brand_category')
            ? ratib_brand_category() . ' — recruitment orchestration, workforce tracking, compliance, and finance-grade operations on one multi-tenant platform.'
            : 'Enterprise Workforce Program Infrastructure — recruitment orchestration, workforce tracking, compliance, and finance-grade operations.';
    }
}
