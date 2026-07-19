<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\MobileAppConfigService;

/**
 * Thin adapter: tenant branding + feature flags for Workforce / HR Mobile clients.
 * company_id MUST come from TenantContext (API token) — never from client input.
 */
final class MobileConfigController extends Controller
{
    public function config(): void
    {
        // Reject client-supplied company overrides if present.
        if (isset($_GET['company_id']) || isset($_POST['company_id'])) {
            Response::json([
                'success' => false,
                'message' => 'company_id must not be supplied by the client',
            ], 400);
            return;
        }

        $companyId = (int) (TenantContext::companyId() ?? 0);
        $result = (new MobileAppConfigService())->apiConfigForCompany($companyId);
        Response::json($result['body'], (int) $result['status']);
    }
}
