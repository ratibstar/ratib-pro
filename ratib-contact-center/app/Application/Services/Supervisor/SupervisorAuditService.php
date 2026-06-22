<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Supervisor;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class SupervisorAuditService
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
                'rtype' => $resourceType ?? 'supervisor',
                'rid' => $resourceId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[RCC SupervisorAudit] ' . $e->getMessage());
        }

        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_AUDIT_LOGGED,
            'tenant_id' => $tenantId,
            'payload' => ['action' => $action],
        ]);
    }
}
