<?php
declare(strict_types=1);

/**
 * HR Mobile console — configuration only (no business logic).
 *
 * Feature flag (default false): rateb_system_settings.hr_mobile_console_enabled
 *   Read via rateb_hr_mobile_console_flag_enabled() — NOT getenv / dotenv.
 *
 * Flutter Web URL (optional launch target; still env — not the availability flag):
 * - RATEB_HR_MOBILE_WEB_URL
 *
 * Optional:
 * - RATEB_HR_MOBILE_API_BASE
 * - RATEB_HR_MOBILE_BUILD
 *
 * Access: flag ON + Super Admin + settings.manage (see rateb_hr_mobile_console_accessible).
 *
 * @return array{
 *   flag_enabled: bool,
 *   enabled: bool,
 *   web_url: string,
 *   api_base: string,
 *   build: string,
 *   environment: string,
 *   app_debug: bool
 * }
 */
return static function (): array {
    $flagEnabled = function_exists('rateb_hr_mobile_console_flag_enabled')
        ? rateb_hr_mobile_console_flag_enabled()
        : false;

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
        'enabled' => $flagEnabled,
        'web_url' => $webUrl,
        'api_base' => $apiBase,
        'build' => $build,
        'environment' => $envLabel !== '' ? $envLabel : 'production',
        'app_debug' => false,
    ];
};
