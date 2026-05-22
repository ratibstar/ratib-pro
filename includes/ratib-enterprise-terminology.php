<?php
/**
 * Enterprise terminology map — public and in-app user-facing labels (RATEB).
 */
declare(strict_types=1);

if (!function_exists('ratib_enterprise_term')) {
    function ratib_enterprise_term(string $legacy): string
    {
        static $map = [
            'Dashboard' => 'Operations Control Plane',
            'dashboard' => 'operations control plane',
            'Reports' => 'Executive Telemetry',
            'reports' => 'executive telemetry',
            'Notifications' => 'Operational Signaling',
            'notifications' => 'operational signaling',
            'Settings' => 'System Settings',
            'settings' => 'system settings',
            'Admin Panel' => 'Platform Control Plane',
            'admin panel' => 'platform control plane',
            'GPS Tracking' => 'Geospatial Workforce Telemetry',
            'GPS tracking' => 'geospatial workforce telemetry',
            'Tracking' => 'Workforce Telemetry',
            'Worker Files' => 'Workforce System of Record',
            'Map' => 'Geospatial Operations Console',
            'Agency Portal' => 'Agency Operations Workspace',
            'CRM' => 'Workforce Operations Infrastructure',
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
            ? ratib_brand_category() . ' — recruitment orchestration, workforce telemetry, compliance, and finance-grade operations on one multi-tenant control plane.'
            : 'Enterprise Workforce Program Infrastructure — recruitment orchestration, workforce telemetry, compliance, and finance-grade operations.';
    }
}
