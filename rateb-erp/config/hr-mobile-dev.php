<?php
declare(strict_types=1);

/**
 * HR Mobile development console — configuration only (no business logic).
 *
 * Env keys (no hardcoded URLs):
 * - RATEB_HR_MOBILE_WEB_URL   Flutter Web base (e.g. http://localhost:8080)
 * - RATEB_HR_MOBILE_API_BASE  Optional API base override
 * - RATEB_HR_MOBILE_BUILD     Optional build/version label
 *
 * Visibility: APP_ENV|RATEB_ENV=development (or local/dev) OR APP_DEBUG=true
 * Never when APP_ENV|RATEB_ENV is production/prod.
 *
 * @return array{
 *   enabled: bool,
 *   web_url: string,
 *   api_base: string,
 *   build: string,
 *   environment: string,
 *   app_debug: bool
 * }
 */
return static function (): array {
    $envRaw = strtolower(trim((string) (getenv('RATEB_ENV') ?: getenv('APP_ENV') ?: '')));
    $debugRaw = getenv('APP_DEBUG');
    $appDebug = $debugRaw !== false && filter_var((string) $debugRaw, FILTER_VALIDATE_BOOLEAN);

    $isProdEnv = in_array($envRaw, ['production', 'prod'], true);
    $isDevEnv = in_array($envRaw, ['development', 'dev', 'local'], true);
    if ($isProdEnv) {
        $enabled = false;
    } elseif ($isDevEnv) {
        $enabled = true;
    } elseif ($appDebug) {
        // APP_DEBUG alone: allow only when host is not production-inferred.
        $enabled = !(function_exists('rateb_is_production') && rateb_is_production());
    } else {
        $enabled = false;
    }

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

    $environment = $envRaw !== '' ? $envRaw : ($appDebug ? 'debug' : 'unknown');

    return [
        'enabled' => $enabled,
        'web_url' => $webUrl,
        'api_base' => $apiBase,
        'build' => $build,
        'environment' => $environment,
        'app_debug' => $appDebug,
    ];
};
