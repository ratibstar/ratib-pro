<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssEmployeeResolverService;

/**
 * Pure thin adapter for ESS me identity.
 * Auth context from middleware; resolution owned by HrEssEmployeeResolverService.
 */
final class HrEssMeController extends Controller
{
    public function me(): void
    {
        $result = (new HrEssEmployeeResolverService())->resolveCurrentEmployee(
            TenantContext::apiUserId(),
            TenantContext::companyId()
        );
        Response::json($result['body'], (int) $result['status']);
    }
}
