<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Always-on commercial lock: control_agencies.is_suspended blocks tenant ERP.
 * Independent of SUBSCRIPTION_ENFORCEMENT_ENABLED (that flag stays for the engine).
 */
final class AgencyCommercialLock
{
    /** @var list<string> */
    private const ALLOW_FRAGMENTS = [
        'logout',
        'subscription/renew',
        'subscription/invoices',
        'subscription/payment-status',
        'subscription/payment',
    ];

    public static function enforceHttp(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (defined('RATEB_HEALTH_PROBE') && RATEB_HEALTH_PROBE) {
            return;
        }
        if (defined('RATEB_SKIP_AGENCY_COMMERCIAL_LOCK') && RATEB_SKIP_AGENCY_COMMERCIAL_LOCK) {
            return;
        }
        if (defined('RATEB_WEBSITE_KERNEL') && RATEB_WEBSITE_KERNEL) {
            return;
        }

        $agencyId = defined('RATEB_ERP_AGENCY_ID') ? (int) RATEB_ERP_AGENCY_ID : 0;
        if ($agencyId < 1) {
            return;
        }
        if (!function_exists('rateb_control_agency_is_commercially_suspended')) {
            $lookup = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
            if (is_file($lookup)) {
                require_once $lookup;
            }
        }
        if (!function_exists('rateb_control_agency_is_commercially_suspended')) {
            return;
        }
        if (!rateb_control_agency_is_commercially_suspended($agencyId)) {
            return;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if (self::isAllowListed($uri)) {
            return;
        }

        self::halt($agencyId, $uri);
    }

    public static function isAllowListed(string $requestUri): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $requestUri));
        $path = parse_url($normalized, PHP_URL_PATH);
        $hay = is_string($path) && $path !== '' ? $path : $normalized;
        foreach (self::ALLOW_FRAGMENTS as $frag) {
            if ($hay === $frag
                || substr($hay, -strlen('/' . $frag)) === '/' . $frag
                || strpos($hay, '/' . $frag) !== false
                || strpos($hay, $frag) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function halt(int $agencyId, string $uri): void
    {
        error_log(sprintf(
            'RATEB agency commercial lock: agency_id=%d uri=%s',
            $agencyId,
            $uri
        ));

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $wantsJson = strpos($uri, '/api/') !== false
            || strpos($accept, 'application/json') !== false
            || isset($_SERVER['HTTP_X_CSRF_TOKEN']);

        http_response_code(403);
        if ($wantsJson) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'This agency is suspended. ERP access is blocked until the subscription is reactivated.',
                'code' => 'AGENCY_SUSPENDED',
            ]);
            exit;
        }

        $view = (defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2)) . '/views/errors/agency-suspended.php';
        if (is_file($view)) {
            $agencyName = '';
            if (function_exists('rateb_lookup_agency_by_id')) {
                $row = rateb_lookup_agency_by_id($agencyId);
                $agencyName = is_array($row) ? trim((string) ($row['name'] ?? '')) : '';
            }
            require $view;
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>Agency suspended</h1><p>ERP is blocked until this agency is unsuspended in Control Panel.</p>';
        exit;
    }
}
