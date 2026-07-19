<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\NotificationService;

/**
 * Thin ESS adapter — notification list only.
 * ONE service: NotificationService::listForUser
 */
final class HrEssNotificationsController extends Controller
{
    public function list(): void
    {
        $items = (new NotificationService())->listForUser(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json([
            'success' => true,
            'notifications' => $items,
        ]);
    }
}
