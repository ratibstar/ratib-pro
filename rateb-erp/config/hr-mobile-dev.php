<?php
declare(strict_types=1);

/**
 * HR Mobile console — configuration / feature flag only (no business logic).
 *
 * Feature flag (default false):
 * - HR_MOBILE_CONSOLE_ENABLED=true|false
 *   (alias: RATEB_HR_MOBILE_CONSOLE_ENABLED)
 *
 * Flutter Web URL (required to launch):
 * - RATEB_HR_MOBILE_WEB_URL
 *
 * Optional:
 * - RATEB_HR_MOBILE_API_BASE
 * - RATEB_HR_MOBILE_BUILD
 *
 * Visibility is NOT based on APP_ENV / APP_DEBUG / rateb_is_production().
 * Access is: flag ON + Super Admin + settings.manage (existing platform admin permission;
 * there is no system.development slug in the catalog without a new RBAC migration).
 *
 * @return array{
 *   flag_enabled: bool,
 *   web_url: string,
 *   api_base: string,
 *   build: string,
 *   environment: string
 * }
 */
return static function (): array {
    $flagRaw = getenv('HR_MOBILE_CONSOLE_ENABLED');
    if ($flagRaw === false || $flagRaw === '') {
        $flagRaw = getenv('RATEB_HR_MOBILE_CONSOLE_ENABLED');
    }
    $flagEnabled = $flagRaw !== false
        && $flagRaw !== ''
        && filter_var((string) $flagRaw, FILTER_VALIDATE_BOOLEAN);

    $webUrl = rtrim(trim((string) (getenv('RATEB_HR_MOBILE_WEB_URL') ?: '')), '/');
    $apiOverride = rtrim(trim((string) (getenv('RATEB_HR_MOBILE_API_BASE') ?: '')), '/');
    $apiBase = $apiOverride;
    if ($apiBase === '' && function_exists('rateb_site_origin') && function_exists('rateb_erp_app_prefix')) {
        $apiBase = rtrim(rateb_site_origin() . rateb_erp_app_prefix(), '/') . '/api/v1';
    }

    $build = trim((string) (getenv('RATEB_HR_MOBILE_BUILD') ?: ''));
    if ($build === '') {
        $build = 'dev';
    }

    $envLabel = strtolower(trim((string) (getenv('RATEB_ENV') ?: getenv('APP_ENV') ?: 'production')));

    return [
        'flag_enabled' => $flagEnabled,
        'enabled' => $flagEnabled, // backward-compatible key (flag only; not access)
        'web_url' => $webUrl,
        'api_base' => $apiBase,
        'build' => $build,
        'environment' => $envLabel !== '' ? $envLabel : 'production',
        'app_debug' => false,
    ];
};
