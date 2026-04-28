<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/middleware/TenantResolverMiddleware.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/middleware/TenantResolverMiddleware.php`.
 */
/**
 * TenantResolverMiddleware
 *
 * Domain-only tenant resolution (HTTP_HOST). No session/query/body overrides.
 *
 * Enable with env var:
 *   DOMAIN_TENANT_RESOLUTION_ENABLED=1
 *
 * Optional:
 *   DOMAIN_TENANT_NOT_FOUND_STATUS=404|403   (default 404)
 *   DOMAIN_TENANT_LOG=1|true                 (default enabled)
 */
if (defined('DOMAIN_TENANT_RESOLUTION_MIDDLEWARE_LOADED')) {
    return;
}
define('DOMAIN_TENANT_RESOLUTION_MIDDLEWARE_LOADED', true);

require_once __DIR__ . '/../../api/models/Tenant.php';

final class TenantResolverMiddleware
{
    private const GUARD = 'DOMAIN_TENANT_RESOLUTION_HANDLED';

    public static function handle(): void
    {
        if (defined(self::GUARD)) {
            return;
        }
        define(self::GUARD, true);

        if (!self::isEnabled()) {
            return;
        }

        if (self::shouldSkipForSapi()) {
            return;
        }

        $rawHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $host = self::normalizeHost($rawHost);
        if ($host === '') {
            self::fail('Missing host', 400);
        }

        $tenant = Tenant::findByDomain($host);
        if (!$tenant || empty($tenant['id'])) {
            self::fail('Tenant not found for host', self::notFoundStatus());
        }

        $tenantId = (int) $tenant['id'];
        if ($tenantId <= 0) {
            self::fail('Tenant not found for host', self::notFoundStatus());
        }

        // Only active tenants are routable.
        $status = strtolower(trim((string) ($tenant['status'] ?? '')));
        if ($status !== 'active') {
            self::fail('Tenant not active for host', self::notFoundStatus());
        }

        if (!defined('REQUEST_TENANT_ID')) {
            define('REQUEST_TENANT_ID', $tenantId);
        }
        $_SERVER['RATIB_REQUEST_TENANT_ID'] = (string) $tenantId;

        $GLOBALS['RATIB_REQUEST_CONTEXT'] = array(
            'tenant_id' => $tenantId,
            'tenant_domain' => $host,
        );

        self::logResolved($host, $tenantId);
    }

    public static function requestTenantId(): ?int
    {
        if (defined('REQUEST_TENANT_ID')) {
            return (int) REQUEST_TENANT_ID;
        }
        $srv = (string) ($_SERVER['RATIB_REQUEST_TENANT_ID'] ?? '');
        if ($srv !== '' && ctype_digit($srv)) {
            return (int) $srv;
        }
        return null;
    }

    private static function isEnabled(): bool
    {
        if (defined('DOMAIN_TENANT_RESOLUTION_ENABLED')) {
            return (bool) DOMAIN_TENANT_RESOLUTION_ENABLED;
        }
        $v = getenv('DOMAIN_TENANT_RESOLUTION_ENABLED');
        return $v === '1' || strtolower((string) $v) === 'true';
    }

    private static function notFoundStatus(): int
    {
        $v = getenv('DOMAIN_TENANT_NOT_FOUND_STATUS');
        $v = $v === false ? '404' : strtolower(trim((string) $v));
        if ($v === '403') {
            return 403;
        }
        return 404;
    }

    private static function loggingEnabled(): bool
    {
        $v = getenv('DOMAIN_TENANT_LOG');
        if ($v === false) {
            return true;
        }
        $v = strtolower(trim((string) $v));
        return !($v === '0' || $v === 'false' || $v === 'off' || $v === 'no');
    }

    private static function shouldSkipForSapi(): bool
    {
        return php_sapi_name() === 'cli';
    }

    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }
        if (strpos($host, ':') !== false) {
            $host = explode(':', $host, 2)[0];
        }
        return $host;
    }

    private static function logResolved(string $host, int $tenantId): void
    {
        if (!self::loggingEnabled()) {
            return;
        }
        error_log('tenant_resolve host=' . $host . ' tenant_id=' . (string) $tenantId);
    }

    private static function fail(string $message, int $status): void
    {
        if (self::loggingEnabled()) {
            error_log('tenant_resolve_fail status=' . (string) $status . ' msg=' . $message);
        }

        http_response_code($status);

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $isApi = (strpos($uri, '/api/') !== false);
        if ($isApi) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Service unavailable for this host',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        header('Content-Type: text/plain; charset=UTF-8');
        echo "Service unavailable for this host\n";
        exit;
    }
}

if (!function_exists('ratib_request_tenant_id')) {
    function ratib_request_tenant_id(): ?int
    {
        if (class_exists('TenantExecutionContext', false) && TenantExecutionContext::isInitialized()) {
            return TenantExecutionContext::getTenantId();
        }
        return TenantResolverMiddleware::requestTenantId();
    }
}

if (!function_exists('ratib_request_context')) {
    /**
     * @return array{tenant_id?: int, tenant_domain?: string}|null
     */
    function ratib_request_context(): ?array
    {
        if (class_exists('TenantExecutionContext', false) && TenantExecutionContext::isInitialized()) {
            $tid = TenantExecutionContext::getTenantId();
            if ($tid !== null && $tid > 0) {
                $domain = (string) ($GLOBALS['RATIB_REQUEST_CONTEXT']['tenant_domain'] ?? '');
                return ['tenant_id' => $tid, 'tenant_domain' => $domain];
            }
        }
        if (!empty($GLOBALS['RATIB_REQUEST_CONTEXT']) && is_array($GLOBALS['RATIB_REQUEST_CONTEXT'])) {
            return $GLOBALS['RATIB_REQUEST_CONTEXT'];
        }
        return null;
    }
}
