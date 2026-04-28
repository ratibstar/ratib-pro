<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/AdminAuditLog.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/AdminAuditLog.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

/**
 * Persists admin actions to centralized system_events (control DB).
 */
final class AdminAuditLog
{
    public static function write(PDO $controlPdo, string $action, array $context = []): void
    {
        $userId = $context['user_id'] ?? null;
        $role = (string) ($context['role'] ?? '');
        $tenantId = isset($context['tenant_id']) ? ($context['tenant_id'] !== null ? (int) $context['tenant_id'] : null) : null;
        $payload = $context['payload'] ?? null;
        $ip = (string) ($context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));

        try {
            emitEvent('ADMIN_AUDIT', 'info', 'Admin action: ' . $action, [
                'tenant_id' => $tenantId,
                'user_id' => ($userId !== null && (int) $userId > 0) ? (int) $userId : null,
                'source' => 'admin_audit',
                'action' => substr($action, 0, 128),
                'role' => substr($role, 0, 32),
                'ip_address' => substr($ip, 0, 45),
                'payload' => $payload,
            ], $controlPdo);
        } catch (Throwable $e) {
            error_log('AdminAuditLog::write failed: ' . $e->getMessage());
        }
    }
}

function logAdminAudit(PDO $controlPdo, string $action, array $context = []): void
{
    AdminAuditLog::write($controlPdo, $action, $context);
}
