<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssPhaseCService;

/** Thin ESS settings adapter (change-password only). */
final class HrEssSettingsController extends Controller
{
    public function changePassword(): void
    {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $payload = is_array($decoded) ? $decoded : [];
        $result = (new HrEssPhaseCService())->changePassword(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $payload
        );
        Response::json($result['body'], (int) $result['status']);
    }
}
