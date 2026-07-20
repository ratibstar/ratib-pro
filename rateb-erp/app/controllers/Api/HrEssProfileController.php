<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssProfileService;

/**
 * Thin ESS profile adapter — identity via HrEssEmployeeResolverService only.
 */
final class HrEssProfileController extends Controller
{
    public function show(): void
    {
        $result = (new HrEssProfileService())->getProfile(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json($result['body'], (int) $result['status']);
    }
}
