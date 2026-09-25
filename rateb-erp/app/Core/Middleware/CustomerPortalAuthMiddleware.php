<?php
declare(strict_types=1);

namespace Rateb\App\Core\Middleware;

use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\CustomerPortal\CustomerPortalAuthService;
use Rateb\App\CustomerPortal\CustomerPortalContext;
require_once __DIR__ . '/Middleware.php';

/**
 * Bearer guard for Customer Portal app routes (rcp_ tokens only).
 * Independent of ApiAuthMiddleware / staff API tokens.
 */
final class CustomerPortalAuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        CustomerPortalContext::clear();

        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        $bearer = preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m) ? $m[1] : '';

        try {
            $result = (new CustomerPortalAuthService())->authenticate(
                $bearer,
                (string) ($_SERVER['HTTP_HOST'] ?? ''),
                self::companySlug()
            );
        } catch (\Throwable $e) {
            error_log('customer_portal_auth reason=exception class=' . get_class($e));
            Response::json(['success' => false, 'code' => 'service_unavailable', 'message' => 'Service unavailable'], 503);

            return false;
        }

        if (empty($result['ok']) || !isset($result['context'])) {
            Response::json($result['body'] ?? ['success' => false, 'code' => 'unauthorized', 'message' => 'Unauthorized'], (int) ($result['status'] ?? 401));

            return false;
        }

        CustomerPortalContext::set($result['context']);
        TenantContext::setSuperAdmin(false);
        TenantContext::setCompanyId((int) $result['context']['company']['id']);

        return true;
    }

    /** Central host only: company slug from X-Rateb-Company header or ?company= query. */
    public static function companySlug(): ?string
    {
        $slug = $_SERVER['HTTP_X_RATEB_COMPANY'] ?? $_GET['company'] ?? null;

        return is_string($slug) ? trim($slug) : null;
    }
}
