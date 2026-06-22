<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

/** Central audit logger for all RCC mutations (Phase 10+). */
final class RccAuditService
{
    public function log(
        int $tenantId,
        string $action,
        ?int $userId = null,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $payload = null
    ): void {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO rcc_audit_logs (tenant_id, user_id, action, resource_type, resource_id, ip_address, payload)
                 VALUES (:tid, :uid, :action, :rtype, :rid, :ip, :payload)'
            );
            $stmt->execute([
                'tid' => $tenantId,
                'uid' => $userId,
                'action' => $action,
                'rtype' => $resourceType,
                'rid' => $resourceId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[RCC Audit] ' . $e->getMessage());
        }

        EventBus::instance()->emit([
            'type' => EventType::OPS_AUDIT_LOGGED,
            'tenant_id' => $tenantId,
            'payload' => ['action' => $action, 'resource_type' => $resourceType],
        ]);
    }
}
