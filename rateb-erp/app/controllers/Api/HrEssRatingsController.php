<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssPhaseCService;

/** Thin ESS ratings adapter (read-only). */
final class HrEssRatingsController extends Controller
{
    public function summary(): void
    {
        $result = (new HrEssPhaseCService())->ratings(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json($result['body'], (int) $result['status']);
    }
}
