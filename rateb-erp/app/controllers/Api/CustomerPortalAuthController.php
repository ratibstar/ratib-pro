<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\CustomerPortal\CustomerPortalAuthService;
use Rateb\App\CustomerPortal\CustomerPortalContext;

/**
 * Customer Portal app auth: login, logout, current session.
 * No registration endpoint by design — accounts must already exist and be approved.
 */
final class CustomerPortalAuthController extends Controller
{
    public function login(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $body = $_POST;
        }

        try {
            $result = (new CustomerPortalAuthService())->login(
                [
                    'email' => $body['email'] ?? '',
                    'password' => $body['password'] ?? '',
                    'portal_type' => $body['portal_type'] ?? '',
                    'company' => $body['company'] ?? '',
                ],
                (string) ($_SERVER['HTTP_HOST'] ?? ''),
                (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
            );
        } catch (\Throwable $e) {
            error_log('customer_portal_auth reason=exception class=' . get_class($e));
            Response::json(['success' => false, 'code' => 'service_unavailable', 'message' => 'Service unavailable'], 503);

            return;
        }

        Response::json($result['body'], $result['status']);
    }

    public function logout(): void
    {
        try {
            (new CustomerPortalAuthService())->logout(CustomerPortalContext::tokenId());
        } catch (\Throwable $e) {
            error_log('customer_portal_auth reason=logout_exception class=' . get_class($e));
            Response::json(['success' => false, 'code' => 'service_unavailable', 'message' => 'Service unavailable'], 503);

            return;
        }
        CustomerPortalContext::clear();
        Response::json(['success' => true]);
    }

    public function session(): void
    {
        $ctx = CustomerPortalContext::current();
        if ($ctx === null) {
            Response::json(['success' => false, 'code' => 'unauthorized', 'message' => 'Unauthorized'], 401);

            return;
        }
        Response::json([
            'success' => true,
            'account' => $ctx['account'],
            'company' => $ctx['company'],
            'token' => ['expires_at' => $ctx['token_expires_at']],
        ]);
    }
}
