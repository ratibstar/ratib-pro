<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\NotificationService;

/**
 * Thin ESS adapter — notification list + mark read (NotificationService only).
 */
final class HrEssNotificationsController extends Controller
{
    public function list(): void
    {
        $items = (new NotificationService())->listForUser(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        $type = strtolower(trim((string) $this->input('type', '')));
        if ($type !== '' && $type !== 'all') {
            $items = array_values(array_filter(
                $items,
                static function ($row) use ($type): bool {
                    if (!is_array($row)) {
                        return false;
                    }
                    $rowType = strtolower((string) ($row['type'] ?? $row['trigger_type'] ?? ''));

                    return $rowType === $type || str_contains($rowType, $type);
                }
            ));
        }
        Response::json([
            'success' => true,
            'notifications' => $items,
        ]);
    }

    public function markRead(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $ok = (new NotificationService())->markRead($id, (int) TenantContext::apiUserId());
        if ($ok) {
            Response::json(['success' => true, 'code' => 'ok', 'message' => 'ok']);
            return;
        }
        Response::json([
            'success' => false,
            'code' => 'notification_not_found',
            'message' => 'Notification not found',
        ], 404);
    }

    public function markAllRead(): void
    {
        $n = (new NotificationService())->markAllReadForUser(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json([
            'success' => true,
            'code' => 'ok',
            'message' => 'ok',
            'updated' => $n,
        ]);
    }
}
