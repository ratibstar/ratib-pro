<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssPhaseCService;

/** Thin ESS payment-methods architecture adapter — no processing. */
final class HrEssPaymentMethodsController extends Controller
{
    public function list(): void
    {
        $result = (new HrEssPhaseCService())->paymentMethods(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json($result['body'], (int) $result['status']);
    }
}
